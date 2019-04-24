<?php

namespace Yiisoft\Yii\AuthClient\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Yii\AuthClient\OAuth2;

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
            ->setConstructorArgs([null, $httpClient, $requestFactory])
            ->setMethods(['initUserAttributes', 'getName', 'getTitle'])
            ->getMock();
        return $oauthClient;
    }

    // Tests :
    /**
     * @runInSeparateProcess
     */
    public function testBuildAuthUrl()
    {
        $oauthClient = $this->createClient();
        $authUrl = 'http://test.auth.url';
        $oauthClient->authUrl = $authUrl;
        $clientId = 'test_client_id';
        $oauthClient->clientId = $clientId;
        $returnUrl = 'http://test.return.url';
        $oauthClient->setReturnUrl($returnUrl);

        $builtAuthUrl = $oauthClient->buildAuthUrl();

        $this->assertContains($authUrl, $builtAuthUrl, 'No auth URL present!');
        $this->assertContains($clientId, $builtAuthUrl, 'No client id present!');
        $this->assertContains(rawurlencode($returnUrl), $builtAuthUrl, 'No return URL present!');
    }
}
