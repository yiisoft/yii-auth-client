<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Factory\Factory;
use Yiisoft\Json\Json;
use Yiisoft\Yii\AuthClient\OAuth1;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\Signature\Signature;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class OAuth1Test extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    /**
     * Creates test OAuth1 client instance.
     *
     * @return OAuth1 oauth client.
     */
    protected function createClient(): OAuth1
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        return $this->getMockBuilder(OAuth1::class)
            ->setConstructorArgs(
                [$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session()), new Factory()]
            )
            ->onlyMethods(['initUserAttributes', 'getName', 'getTitle'])
            ->getMockForAbstractClass();
    }

    // Tests :

    public function testSignRequest(): void
    {
        $oauthClient = $this->createClient();

        $request = $oauthClient->createRequest('GET', 'https://example.com?s=some&a=another');

        /* @var $oauthSignatureMethod Signature|MockObject */
        $oauthSignatureMethod = $this->getMockBuilder(Signature::class)
            ->onlyMethods(['getName', 'generateSignature'])
            ->addMethods(['setConsumerKey', 'setConsumerSecret'])
            ->getMock();
        $oauthSignatureMethod->expects($this->any())
            ->method('getName')
            ->willReturn('test');
        $oauthSignatureMethod->expects($this->any())
            ->method('generateSignature')
            ->willReturnArgument(0);
        $oauthSignatureMethod->method('setConsumerSecret')->with('test_secret');
        $oauthSignatureMethod->method('setConsumerKey')->with('test_key');

        $oauthClient->setSignatureMethod($oauthSignatureMethod);

        $request = $oauthClient->signRequest($request);

        $signedParams = RequestUtil::getParams($request);

        $this->assertNotEmpty($signedParams['oauth_signature'], 'Unable to sign request!');

        $parts = [
            'GET',
            'https://example.com',
            http_build_query(
                [
                    'a' => 'another',
                    'oauth_nonce' => $signedParams['oauth_nonce'],
                    'oauth_signature_method' => $signedParams['oauth_signature_method'],
                    'oauth_timestamp' => $signedParams['oauth_timestamp'],
                    'oauth_version' => $signedParams['oauth_version'],
                    's' => 'some',
                ]
            ),
        ];
        $parts = array_map('rawurlencode', $parts);
        $expectedSignature = implode('&', $parts);

        $this->assertEquals($expectedSignature, $signedParams['oauth_signature'], 'Invalid base signature string!');
    }

    /**
     * @depends testSignRequest
     */
    public function testAuthorizationHeaderMethods(): void
    {
        $oauthClient = $this->createClient();

        $request = $oauthClient->createRequest('POST', 'http://example.com/');
        $request = $oauthClient->signRequest($request);
        $this->assertNotEmpty($request->getHeaderLine('Authorization'));

        $request = $oauthClient->createRequest('GET', 'http://example.com/');
        $request = $oauthClient->signRequest($request);
        $this->assertEmpty($request->getHeaderLine('Authorization'));

        $oauthClient->setAuthorizationHeaderMethods(['GET']);
        $request = $oauthClient->createRequest('GET', 'http://example.com/');
        $request = $oauthClient->signRequest($request);
        $this->assertNotEmpty($request->getHeaderLine('Authorization'));

        $oauthClient->setAuthorizationHeaderMethods(null);
        $request = $oauthClient->createRequest('GET', 'http://example.com/');
        $request = $oauthClient->signRequest($request);
        $this->assertNotEmpty($request->getHeaderLine('Authorization'));

        $oauthClient->setAuthorizationHeaderMethods([]);
        $request = $oauthClient->createRequest('POST', 'http://example.com/');
        $request = $oauthClient->signRequest($request);
        $this->assertEmpty($request->getHeaderLine('Authorization'));
    }

    /**
     * Data provider for {@see testComposeAuthorizationHeader()}.
     *
     * @return array test data.
     */
    public function composeAuthorizationHeaderDataProvider(): array
    {
        return [
            [
                '',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'oauth_test_name_2' => 'oauth_test_value_2',
                ],
                ['Authorization' => 'OAuth oauth_test_name_1="oauth_test_value_1", oauth_test_name_2="oauth_test_value_2"'],
            ],
            [
                'test_realm',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'oauth_test_name_2' => 'oauth_test_value_2',
                ],
                ['Authorization' => 'OAuth realm="test_realm", oauth_test_name_1="oauth_test_value_1", oauth_test_name_2="oauth_test_value_2"'],
            ],
            [
                '',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'test_name_2' => 'test_value_2',
                ],
                ['Authorization' => 'OAuth oauth_test_name_1="oauth_test_value_1"'],
            ],
        ];
    }

    /**
     * @dataProvider composeAuthorizationHeaderDataProvider
     *
     * @param string $realm authorization realm.
     * @param array $params request params.
     * @param array $expectedAuthorizationHeader expected authorization header.
     */
    public function testComposeAuthorizationHeader(
        string $realm,
        array $params,
        array $expectedAuthorizationHeader
    ): void {
        $oauthClient = $this->createClient();
        $authorizationHeader = $oauthClient->composeAuthorizationHeader($params, $realm);
        $this->assertEquals($expectedAuthorizationHeader, $authorizationHeader);
    }

    public function testBuildAuthUrl(): void
    {
        $requestTokenToken = 'test_request_token';
        $content = Stream::create(
            Json::encode(
                [
                    'class' => OAuthToken::class,
                    'setToken()' => [$requestTokenToken],
                ]
            )
        );
        $response = $this->getRequestFactory()->createResponse()->withBody($content);

        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();
        $httpClient->method('sendRequest')->willReturn($response);

        $oauthClient = $this->getMockBuilder(OAuth1::class)
            ->setConstructorArgs(
                [$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session()), new Factory()]
            )
            ->onlyMethods(['initUserAttributes', 'getName', 'getTitle'])
            ->getMockForAbstractClass();

        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $oauthClient->setRequestTokenUrl('http://token.url');
        $serverRequest = new ServerRequest('GET', 'http://test.local');

        $builtAuthUrl = $oauthClient->buildAuthUrl($serverRequest->withBody(Stream::create('')));

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($requestTokenToken, $builtAuthUrl, 'No token present!');
    }
}
