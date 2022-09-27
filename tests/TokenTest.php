<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\OAuthToken;

class TokenTest extends TestCase
{
    public function testSetupParams(): void
    {
        $oauthToken = new OAuthToken();

        $params = [
            'name_1' => 'value_1',
            'name_2' => 'value_2',
        ];
        $oauthToken->setParams($params);
        $this->assertEquals($params, $oauthToken->getParams(), 'Unable to setup params!');

        $newParamName = 'new_param_name';
        $newParamValue = 'new_param_value';
        $oauthToken->setParam($newParamName, $newParamValue);
        $this->assertEquals($newParamValue, $oauthToken->getParam($newParamName), 'Unable to setup param by name!');
    }

    /**
     * @depends testSetupParams
     */
    public function testSetupParamsShortcuts(): void
    {
        $oauthToken = new OAuthToken();

        $token = 'test_token_value';
        $oauthToken->setToken($token);
        $this->assertEquals($token, $oauthToken->getToken(), 'Unable to setup token!');

        $tokenSecret = 'test_token_secret';
        $oauthToken->setTokenSecret($tokenSecret);
        $this->assertEquals($tokenSecret, $oauthToken->getTokenSecret(), 'Unable to setup token secret!');

        $tokenExpireDuration = random_int(1000, 2000);
        $oauthToken->setExpireDuration($tokenExpireDuration);
        $this->assertEquals($tokenExpireDuration, $oauthToken->getExpireDuration(), 'Unable to setup expire duration!');
    }

    /**
     * Data provider for {@see testAutoFetchExpireDuration}.
     *
     * @return array test data.
     */
    public function autoFetchExpireDurationDataProvider(): array
    {
        return [
            [
                ['expire_in' => 123345],
                123345,
            ],
            [
                ['expire' => 233456],
                233456,
            ],
            [
                ['expiry_in' => 34567],
                34567,
            ],
            [
                ['expiry' => 45678],
                45678,
            ],
        ];
    }

    /**
     * @depends      testSetupParamsShortcuts
     * @dataProvider autoFetchExpireDurationDataProvider
     *
     * @param $expectedExpireDuration
     */
    public function testAutoFetchExpireDuration(array $params, $expectedExpireDuration): void
    {
        $oauthToken = new OAuthToken();
        $oauthToken->setParams($params);
        $this->assertEquals($expectedExpireDuration, $oauthToken->getExpireDuration());
    }

    /**
     * @depends testSetupParamsShortcuts
     */
    public function testGetIsExpired(): void
    {
        $oauthToken = new OAuthToken();
        $expireDuration = 3600;
        $oauthToken->setExpireDuration($expireDuration);

        $this->assertFalse($oauthToken->getIsExpired(), 'Not expired token check fails!');

        $oauthToken->setExpireDuration(-$expireDuration);
        $this->assertTrue($oauthToken->getIsExpired(), 'Expired token check fails!');
    }

    /**
     * @depends testGetIsExpired
     */
    public function testGetIsValid(): void
    {
        $oauthToken = new OAuthToken();
        $expireDuration = 3600;
        $oauthToken->setExpireDuration($expireDuration);

        $this->assertFalse($oauthToken->getIsValid(), 'Empty token is valid!');

        $oauthToken->setToken('test_token');
        $this->assertTrue($oauthToken->getIsValid(), 'Filled up token is invalid!');

        $oauthToken->setExpireDuration($oauthToken->getExpireDuration() - $expireDuration);
        $this->assertFalse($oauthToken->getIsValid(), 'Expired token is valid!');
    }
}
