<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class OAuth2Test extends TestCase
{
    /**
     * Creates test OAuth2 client instance.
     *
     * @return OAuth2 oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        $requestFactory = $this->getMockBuilder(RequestFactoryInterface::class)->getMock();

        $yiisoftFactory = new YiisoftFactory(
            new Container(ContainerConfig::create())
        );

        $session = $this->getMockBuilder(Session::class)->getMock();

        $sessionStateStorage = new SessionStateStorage($session);

        return $this->getMockBuilder(OAuth2::class)
            ->setConstructorArgs(
                [$httpClient, $requestFactory, $sessionStateStorage, $yiisoftFactory, $session]
            )
            ->onlyMethods(['getName', 'getTitle', 'getViewOptions', 'getButtonClass', 'getClientId'])
            ->getMock();
    }

    // Tests :

    public function testBuildAuthUrl(): void
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $clientId = 'test_client_id';
        $oauthClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $oauthClient->setOauth2ReturnUrl($returnUrl);
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)->getMock();

        $builtAuthUrl = $oauthClient->buildAuthUrl($serverRequest, []);

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertStringContainsString(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }

    public function testBuildAuthUrlWithAuthParams(): void
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $clientId = 'test_client_id';
        $oauthClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $oauthClient->setOauth2ReturnUrl($returnUrl);
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)->getMock();

        // Set authParams
        $oauthClient->setAuthParams([
            'prompt' => 'select_account',
            'access_type' => 'offline',
        ]);

        $builtAuthUrl = $oauthClient->buildAuthUrl($serverRequest, []);

        $this->assertStringContainsString($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertStringContainsString($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertStringContainsString(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
        $this->assertStringContainsString('prompt=select_account', $builtAuthUrl, 'No prompt parameter present!');
        $this->assertStringContainsString('access_type=offline', $builtAuthUrl, 'No access_type parameter present!');
    }

    public function testAuthParamsCanBeOverriddenByRuntimeParams(): void
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->setAuthUrl($authUrl);
        $clientId = 'test_client_id';
        $oauthClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $oauthClient->setOauth2ReturnUrl($returnUrl);
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)->getMock();

        // Set authParams with a default value
        $oauthClient->setAuthParams([
            'prompt' => 'select_account',
        ]);

        // Override with runtime params
        $builtAuthUrl = $oauthClient->buildAuthUrl($serverRequest, [
            'prompt' => 'consent',
        ]);

        $this->assertStringContainsString('prompt=consent', $builtAuthUrl, 'Runtime params should override authParams!');
        $this->assertStringNotContainsString('prompt=select_account', $builtAuthUrl, 'authParams should be overridden!');
    }
}
