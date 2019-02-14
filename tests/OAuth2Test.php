<?php

namespace yii\authclient\tests;

use yii\authclient\OAuth2;

class OAuth2Test extends \yii\tests\TestCase
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

    /**
     * Creates test OAuth2 client instance.
     * @return OAuth2 oauth client.
     */
    protected function createClient()
    {
        $oauthClient = $this->getMockBuilder(OAuth2::class)
            ->setMethods(['initUserAttributes'])
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
