<?php

namespace yii\authclient\tests\clients;

use yii\authclient\clients\Google;
use yii\authclient\OAuthToken;
use yii\authclient\signature\RsaSha;

/**
 * @group google
 */
class GoogleTest extends \yii\tests\TestCase
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

    public function testAuthenticateUserJwt()
    {
        $params = $this->getParam('google');
        if (empty($params['serviceAccount'])) {
            $this->markTestSkipped("Google service account name is not configured.");
        }

        $oauthClient = new Google();
        $token = $oauthClient->authenticateUserJwt($params['serviceAccount'], [
            '__class' => RsaSha::class,
            'algorithm' => OPENSSL_ALGO_SHA256,
            'privateCertificate' => $params['serviceAccountPrivateKey']
        ]);
        $this->assertTrue($token instanceof OAuthToken);
        $this->assertNotEmpty($token->getToken());
    }
}
