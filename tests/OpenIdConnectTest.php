<?php

namespace yii\authclient\tests;

use yii\authclient\OpenIdConnect;
use yii\cache\ArrayCache;
use yii\cache\Cache;
use yii\helpers\Yii;

class OpenIdConnectTest extends \yii\tests\TestCase
{
    protected function setUp()
    {
        $services = [
            'request' => [
                '__class' => \yii\web\Request::class,
                'hostInfo' => 'http://testdomain.com',
                'scriptUrl' => '/index.php',
            ],
        ];
        $this->mockWebApplication([], null, $services);
    }

    public function testDiscoverConfig()
    {
        $authClient = Yii::createObject([
            '__class' => OpenIdConnect::class,
            'issuerUrl' => 'https://accounts.google.com',
            'cache' => null,
        ]);
        $configParams = $authClient->getConfigParams();
        $this->assertNotEmpty($configParams);
        $this->assertTrue(isset($configParams['authorization_endpoint']));
        $this->assertTrue(isset($configParams['token_endpoint']));

        $this->assertEquals($configParams['token_endpoint'], $authClient->getConfigParam('token_endpoint'));
    }

    /**
     * @depends testDiscoverConfig
     */
    public function testDiscoverConfigCache()
    {
        $cache = new Cache(new ArrayCache());

        $authClient = $this->app->createObject([
            '__class' => OpenIdConnect::class,
            'issuerUrl' => 'https://accounts.google.com',
            'id' => 'google',
            'cache' => $cache,
        ]);
        $cachedConfigParams = $authClient->getConfigParams();

        $authClient = $this->app->createObject([
            '__class' => OpenIdConnect::class,
            'issuerUrl' => 'https://invalid-url.com',
            'id' => 'google',
            'cache' => $cache,
        ]);
        $this->assertEquals($cachedConfigParams, $authClient->getConfigParams());

        $authClient = $this->app->createObject([
            '__class' => OpenIdConnect::class,
            'issuerUrl' => 'https://invalid-url.com',
            'id' => 'foo',
            'cache' => $cache,
        ]);
        $this->expectException(\yii\httpclient\Exception::class);
        $authClient->getConfigParams();
    }

    /**
     * @depends testDiscoverConfig
     */
    public function testBuildAuthUrl()
    {
        $authClient = $this->app->createObject([
            '__class' => OpenIdConnect::class,
            'issuerUrl' => 'https://accounts.google.com',
            'cache' => null,
        ]);
        $clientId = 'test_client_id';
        $authClient->clientId = $clientId;
        $returnUrl = 'http://test.return.url';
        $authClient->setReturnUrl($returnUrl);

        $builtAuthUrl = $authClient->buildAuthUrl();

        $this->assertNotEmpty($authClient->authUrl);
        $this->assertContains($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertContains(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }
}
