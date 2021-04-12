<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\OAuth;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\Signature\PlainText;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class OAuthTestCase extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    /**
     * Creates test OAuth client instance.
     *
     * @return OAuth oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        return $this->getMockBuilder(OAuth::class)
            ->setConstructorArgs(
                [$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session()), new Factory()]
            )
            ->onlyMethods(
                [
                    'composeRequestCurlOptions',
                    'refreshAccessToken',
                    'applyAccessTokenToRequest',
                    'initUserAttributes',
                    'getName',
                    'getTitle',
                ]
            )
            ->getMock();
    }

    // Tests :

    public function testSetGet(): void
    {
        $oauthClient = $this->createClient();
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)->getMock();

        $returnUrl = 'http://test.return.url';
        $oauthClient->setReturnUrl($returnUrl);
        $this->assertEquals(
            $returnUrl,
            $oauthClient->getReturnUrl(
                $serverRequest
            ),
            'Unable to setup return URL!'
        );
    }

    public function testSetupComponents(): void
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

    public function testSetupAccessToken(): void
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
     */
    public function testSetupComponentsByConfig(): void
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
            'class' => PlainText::class,
        ];
        $oauthClient->setSignatureMethod($oauthSignatureMethod);
        $returnedSignatureMethod = $oauthClient->getSignatureMethod();
        $this->assertEquals(
            $oauthSignatureMethod['class'],
            get_class($returnedSignatureMethod),
            'Unable to setup signature method as config!'
        );
    }

    /**
     * @depends testSetupAccessToken
     */
    public function testApiUrl(): void
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
