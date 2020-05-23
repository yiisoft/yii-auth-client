<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\BaseOAuth;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\Signature\PlainText;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

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
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        $oauthClient = $this->getMockBuilder(BaseOAuth::class)
            ->setConstructorArgs(
                [$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session()), new Factory()]
            )
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

        $oauthClient->setAccessToken(['setToken()' => ['token-mock']]);
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
        $testToken = 'test_token';
        $oauthToken = [
            'setToken()' => [$testToken],
            'setTokenSecret()' => ['test_token_secret'],
        ];
        $oauthClient->setAccessToken($oauthToken);
        $this->assertEquals(
            $testToken,
            $oauthClient->getAccessToken()->getToken(),
            'Unable to setup token as config!'
        );

        $oauthSignatureMethod = [
            '__class' => PlainText::class
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
     * @depends testSetupAccessToken
     */
    public function testApiUrl()
    {
        $endpoint = 'http://api.base.url';
        $oauthClient = $this->createClient();
        $oauthClient->setEndpoint($endpoint);

        $accessToken = new OAuthToken();
        $accessToken->setToken('test_access_token');
        $accessToken->setExpireDuration(1000);
        $oauthClient->setAccessToken($accessToken);

        $request = $oauthClient->createApiRequest('GET', '/sub/url');
        $this->assertEquals('http://api.base.url/sub/url', $request->getUri()->__toString());
    }
}
