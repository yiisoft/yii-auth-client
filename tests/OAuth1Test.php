<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Yii\AuthClient\OAuth1;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;
use Yiisoft\Yii\AuthClient\Signature\BaseMethod;
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
     * @return OAuth1 oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        $oauthClient = $this->getMockBuilder(OAuth1::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->setMethods(['initUserAttributes', 'getName', 'getTitle'])
            ->getMockForAbstractClass();
        return $oauthClient;
    }

    // Tests :

    /**
     * @ runInSeparateProcess
     */
    public function testSignRequest()
    {
        $oauthClient = $this->createClient();

        $request = $oauthClient->createRequest('GET', 'https://example.com?s=some&a=another');

        /* @var $oauthSignatureMethod BaseMethod|\PHPUnit\Framework\MockObject\MockObject */
        $oauthSignatureMethod = $this->getMockBuilder(BaseMethod::class)
            ->setMethods(['getName', 'generateSignature'])
            ->getMock();
        $oauthSignatureMethod->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('test'));
        $oauthSignatureMethod->expects($this->any())
            ->method('generateSignature')
            ->will($this->returnArgument(0));

        $oauthClient->setSignatureMethod($oauthSignatureMethod);

        $request = $oauthClient->signRequest($request);

        $signedParams = RequestUtil::getParams($request);

        $this->assertNotEmpty($signedParams['oauth_signature'], 'Unable to sign request!');

        $parts = [
            'GET',
            'https://example.com',
            http_build_query([
                'a' => 'another',
                'oauth_nonce' => $signedParams['oauth_nonce'],
                'oauth_signature_method' => $signedParams['oauth_signature_method'],
                'oauth_timestamp' => $signedParams['oauth_timestamp'],
                'oauth_version' => $signedParams['oauth_version'],
                's' => 'some',
            ])
        ];
        $parts = array_map('rawurlencode', $parts);
        $expectedSignature = implode('&', $parts);

        $this->assertEquals($expectedSignature, $signedParams['oauth_signature'], 'Invalid base signature string!');
    }

    /**
     * @depends testSignRequest
     * @ runInSeparateProcess
     */
    public function testAuthorizationHeaderMethods()
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
     * @return array test data.
     */
    public function composeAuthorizationHeaderDataProvider()
    {
        return [
            [
                '',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'oauth_test_name_2' => 'oauth_test_value_2',
                ],
                ['Authorization' => 'OAuth oauth_test_name_1="oauth_test_value_1", oauth_test_name_2="oauth_test_value_2"']
            ],
            [
                'test_realm',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'oauth_test_name_2' => 'oauth_test_value_2',
                ],
                ['Authorization' => 'OAuth realm="test_realm", oauth_test_name_1="oauth_test_value_1", oauth_test_name_2="oauth_test_value_2"']
            ],
            [
                '',
                [
                    'oauth_test_name_1' => 'oauth_test_value_1',
                    'test_name_2' => 'test_value_2',
                ],
                ['Authorization' => 'OAuth oauth_test_name_1="oauth_test_value_1"']
            ],
        ];
    }

    /**
     * @dataProvider composeAuthorizationHeaderDataProvider
     *
     * @param string $realm authorization realm.
     * @param array $params request params.
     * @param string $expectedAuthorizationHeader expected authorization header.
     */
    public function testComposeAuthorizationHeader($realm, array $params, $expectedAuthorizationHeader)
    {
        $oauthClient = $this->createClient();
        $authorizationHeader = call_user_func_array([$oauthClient, 'composeAuthorizationHeader'], [$params, $realm]);
        $this->assertEquals($expectedAuthorizationHeader, $authorizationHeader);
    }

    public function testBuildAuthUrl()
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);

        $requestTokenToken = 'test_request_token';
        $requestToken = new OAuthToken();
        $requestToken->setToken($requestTokenToken);

        $builtAuthUrl = $oauthClient->buildAuthUrl($requestToken);

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($requestTokenToken, $builtAuthUrl, 'No token present!');
    }
}
