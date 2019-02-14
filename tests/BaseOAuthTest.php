<?php

namespace yii\authclient\tests;

use yii\authclient\signature\PlainText;
use yii\authclient\OAuthToken;
use yii\authclient\BaseOAuth;
use yii\httpclient\Client;

class BaseOAuthTest extends \yii\tests\TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->mockApplication();
    }

    /**
     * Creates test OAuth client instance.
     * @return BaseOAuth oauth client.
     */
    protected function createClient()
    {
        $oauthClient = $this->getMockBuilder(BaseOAuth::class)
            ->setMethods(['composeRequestCurlOptions', 'refreshAccessToken', 'applyAccessTokenToRequest', 'initUserAttributes'])
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

    public function testSetupHttpClient()
    {
        $oauthClient = $this->createClient();
        $oauthClient->apiBaseUrl = 'http://api.test.url';

        $this->assertEquals($oauthClient->apiBaseUrl, $oauthClient->getHttpClient()->baseUrl);

        $httpClient = new Client();
        $oauthClient->setHttpClient($httpClient);
        $actualHttpClient = $oauthClient->getHttpClient();
        $this->assertNotSame($httpClient, $actualHttpClient);
        $this->assertEquals($oauthClient->apiBaseUrl, $actualHttpClient->baseUrl);

        $oauthClient->setHttpClient([
            'transport' => \yii\httpclient\CurlTransport::class
        ]);
        $this->assertEquals($oauthClient->apiBaseUrl, $oauthClient->getHttpClient()->baseUrl);
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
        $this->assertEquals($oauthSignatureMethod, $oauthClient->getSignatureMethod(), 'Unable to setup signature method!');
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
        $this->assertEquals($oauthToken['token'], $oauthClient->getAccessToken()->getToken(), 'Unable to setup token as config!');

        $oauthSignatureMethod = [
            '__class' => \yii\authclient\signature\PlainText::class
        ];
        $oauthClient->setSignatureMethod($oauthSignatureMethod);
        $returnedSignatureMethod = $oauthClient->getSignatureMethod();
        $this->assertEquals($oauthSignatureMethod['__class'], get_class($returnedSignatureMethod), 'Unable to setup signature method as config!');
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
     * @param string $url         request URL.
     * @param array  $params      request params
     * @param string $expectedUrl expected composed URL.
     */
    public function testComposeUrl($url, array $params, $expectedUrl)
    {
        $oauthClient = $this->createClient();
        $composedUrl = $this->invokeMethod($oauthClient, 'composeUrl', [$url, $params]);
        $this->assertEquals($expectedUrl, $composedUrl);
    }

    /**
     * Data provider for [[testApiUrl]].
     * @return array test data.
     */
    public function apiUrlDataProvider()
    {
        return [
            [
                'http://api.base.url',
                'sub/url',
                'http://api.base.url/sub/url',
            ],
            [
                'http://api.base.url',
                'http://api.base.url/sub/url',
                'http://api.base.url/sub/url',
            ],
            [
                'http://api.base.url',
                'https://api.base.url/sub/url',
                'https://api.base.url/sub/url',
            ],
        ];
    }

    /**
     * @depends testSetupAccessToken
     *
     * @dataProvider apiUrlDataProvider
     * @runInSeparateProcess
     * @param $apiBaseUrl
     * @param $apiSubUrl
     * @param $expectedApiFullUrl
     */
    public function testApiUrl($apiBaseUrl, $apiSubUrl, $expectedApiFullUrl)
    {
        $oauthClient = $this->createClient();

        $accessToken = new OAuthToken();
        $accessToken->setToken('test_access_token');
        $accessToken->setExpireDuration(1000);
        $oauthClient->setAccessToken($accessToken);

        $oauthClient->apiBaseUrl = $apiBaseUrl;

        $request = $oauthClient->createApiRequest()
            ->setUrl($apiSubUrl);

        $this->assertEquals($expectedApiFullUrl, $request->getUri()->__toString());
    }
}
