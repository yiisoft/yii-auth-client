<?php

namespace Yiisoft\Yii\AuthClient\Tests\Clients;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\Clients\Google;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\Signature\RsaSha;

/**
 * @group google
 */
class GoogleTest extends TestCase
{
    protected function setUp(): void
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
