<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class OAuth2Test extends TestCase
{
    /**
     * Creates test OAuth2 client instance.
     * @return OAuth2 oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();
        $requestFactory = $this->getMockBuilder(RequestFactoryInterface::class)->getMock();

        $oauthClient = $this->getMockBuilder(OAuth2::class)
            ->setConstructorArgs(
                [$httpClient, $requestFactory, new SessionStateStorage(new Session()), new Session(), new Factory()]
            )
            ->setMethods(['initUserAttributes', 'getName', 'getTitle'])
            ->getMock();
        return $oauthClient;
    }

    // Tests :

    public function testBuildAuthUrl()
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $clientId = 'test_client_id';
        $oauthClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $oauthClient->setReturnUrl($returnUrl);

        $builtAuthUrl = $oauthClient->buildAuthUrl();

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertStringContainsString(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }
}
