<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use Buzz\Client\Curl;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Yii\AuthClient\OpenIdConnect;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\NullCache;

class OpenIdConnectTest extends TestCase
{
    private function getHttpClient(): ClientInterface
    {
        return new Curl(new Psr17Factory());
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    private function getDummyCache(): CacheInterface
    {
        return new NullCache();
    }

    private function getArrayCache(): CacheInterface
    {
        return new ArrayCache();
    }

    private function getOpenIdConnect(CacheInterface $cache, $name = 'google', $issuerUrl = 'https://accounts.google.com')
    {
        $authClient = new OpenIdConnect(null, $name, 'Google', $this->getHttpClient(), $this->getRequestFactory(), $cache);
        $authClient->setIssuerUrl($issuerUrl);
        return $authClient;
    }

    public function testDiscoverConfig()
    {
        $authClient = $this->getOpenIdConnect($this->getDummyCache());

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
        // TODO: what do we test here?!
        $cache = $this->getArrayCache();

        $authClient = $this->getOpenIdConnect($cache);
        $cachedConfigParams = $authClient->getConfigParams();

        $authClient = $this->getOpenIdConnect($cache, 'google', 'https://invalid-url.com');

        $this->assertEquals($cachedConfigParams, $authClient->getConfigParams());

        $authClient = $this->getOpenIdConnect($cache, 'foo', 'https://invalid-url.com');

        //$this->expectException(\yii\httpclient\Exception::class);
        $authClient->getConfigParams();
    }

    /**
     * @depends testDiscoverConfig
     * @runInSeparateProcess
     */
    public function testBuildAuthUrl()
    {
        $authClient = $this->getOpenIdConnect($this->getDummyCache());


        $clientId = 'test_client_id';
        $authClient->setClientId($clientId);
        $returnUrl = 'http://test.return.url';
        $authClient->setReturnUrl($returnUrl);

        $builtAuthUrl = $authClient->buildAuthUrl();

        $this->assertNotEmpty($authClient->getAuthUrl());
        $this->assertStringContainsString($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertStringContainsString(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }
}
