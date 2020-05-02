<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Yii\AuthClient\BaseOAuth;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\Signature\PlainText;

class BaseOAuthTest extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    /**
     * Creates test OAuth client instance.
     * @return BaseOAuth oauth client.
     */
    protected function createClient(?string $endpoint = null)
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        $oauthClient = $this->getMockBuilder(BaseOAuth::class)
            ->setConstructorArgs([$endpoint, $httpClient, $this->getRequestFactory()])
            ->setMethods(
                [
                    'composeRequestCurlOptions',
                    'refreshAccessToken',
                    'applyAccessTokenToRequest',
                    'initUserAttributes',
                    'getName',
                    'getTitle'
                ]
            )
            ->getMock();
        return $oauthClient;
    }

    // Tests :

    public function testSetGet()
    {
        $oauthClient = $this->createClient();

        $returnUrl = 'http://test.return.url';
        $oauthClient->setReturnUrl($returnUrl);
        $this->assertEquals($returnUrl, $oauthClient->getReturnUrl(), 'Unable to setup return URL!');
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetupComponents()
    {
        $oauthClient = $this->createClient();

        $oauthToken = new OAuthToken();
        $oauthClient->setAccessToken($oauthToken);
        $this->assertEquals($oauthToken, $oauthClient->getAccessToken(), 'Unable to setup token!');

        $oauthSignatureMethod = new PlainText();
        $oauthClient->setSignatureMethod($oauthSignatureMethod);
        $this->assertEquals(
            $oauthSignatureMethod,
            $oauthClient->getSignatureMethod(),
            'Unable to setup signature method!'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetupAccessToken()
    {
        $oauthClient = $this->createClient();

        $accessToken = new OAuthToken();
        $oauthClient->setAccessToken($accessToken);

        $this->assertSame($accessToken, $oauthClient->getAccessToken());

        $oauthClient->setAccessToken(['token' => 'token-mock']);
        $accessToken = $oauthClient->getAccessToken();
        $this->assertTrue($accessToken instanceof OAuthToken);
        $this->assertEquals('token-mock', $accessToken->getToken());

        $oauthClient->setAccessToken(null);
        $this->assertNull($oauthClient->getAccessToken());
    }

    /**
     * @depends testSetupComponents
     * @depends testSetupAccessToken
     * @runInSeparateProcess
     */
    public function testSetupComponentsByConfig()
    {
        $oauthClient = $this->createClient();

        $oauthToken = [
            'token' => 'test_token',
            'tokenSecret' => 'test_token_secret',
        ];
        $oauthClient->setAccessToken($oauthToken);
        $this->assertEquals(
            $oauthToken['token'],
            $oauthClient->getAccessToken()->getToken(),
            'Unable to setup token as config!'
        );

        $oauthSignatureMethod = [
            '__class' => \Yiisoft\Yii\AuthClient\Signature\PlainText::class
        ];
        $oauthClient->setSignatureMethod($oauthSignatureMethod);
        $returnedSignatureMethod = $oauthClient->getSignatureMethod();
        $this->assertEquals(
            $oauthSignatureMethod['__class'],
            get_class($returnedSignatureMethod),
            'Unable to setup signature method as config!'
        );
    }

    /**
     * Data provider for [[testComposeUrl()]].
     * @return array test data.
     */
    public function composeUrlDataProvider()
    {
        return [
            [
                'http://test.url',
                [
                    'param1' => 'value1',
                    'param2' => 'value2',
                ],
                'http://test.url?param1=value1&param2=value2',
            ],
            [
                'http://test.url?with=some',
                [
                    'param1' => 'value1',
                    'param2' => 'value2',
                ],
                'http://test.url?with=some&param1=value1&param2=value2',
            ],
            [
                'http://test.url',
                [],
                'http://test.url',
            ],
        ];
    }

    /**
     * @dataProvider composeUrlDataProvider
     *
     * @param string $url request URL.
     * @param array $params request params
     * @param string $expectedUrl expected composed URL.
     */
    public function testComposeUrl($url, array $params, $expectedUrl)
    {
        $oauthClient = $this->createClient();
        $composedUrl = $this->invokeMethod($oauthClient, 'composeUrl', [$url, $params]);
        $this->assertEquals($expectedUrl, $composedUrl);
    }

    /**
     * @depends testSetupAccessToken
     */
    public function testApiUrl()
    {
        $endpoint = 'http://api.base.url';
        $oauthClient = $this->createClient($endpoint);

        $accessToken = new OAuthToken();
        $accessToken->setToken('test_access_token');
        $accessToken->setExpireDuration(1000);
        $oauthClient->setAccessToken($accessToken);

        $request = $oauthClient->createApiRequest('GET', '/sub/url');
        $this->assertEquals('http://api.base.url/sub/url', $request->getUri()->__toString());
    }
}
