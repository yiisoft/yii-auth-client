<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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

    public function testGetIsExpired(): void
    {
        $oauthToken = new OAuthToken();
        $expireDuration = 3600;
        $oauthToken->setExpireDuration($expireDuration);
        /**
         * The token cannot be expired because the expire duration has just been set
         */
        $this->assertFalse($oauthToken->getIsExpired(), 'Not expired token check fails!');
        /**
         * A negative expire duration subtracted from the current timestamp will yield an expired token
         */
        $oauthToken->setExpireDuration(-$expireDuration);
        $this->assertTrue($oauthToken->getIsExpired(), 'Expired token check fails!');
    }

    public function testGetIsValid(): void
    {
        $oauthToken = new OAuthToken();
        $expireDuration = 3600;
        $oauthToken->setExpireDuration($expireDuration);

        $this->assertFalse($oauthToken->getIsValid(), 'Empty token is valid!');

        $oauthToken->setToken('test_token');
        $this->assertTrue($oauthToken->getIsValid(), 'Filled up token is invalid!');

        $oauthToken->setExpireDuration((int) $oauthToken->getExpireDuration() - $expireDuration);
        $this->assertFalse($oauthToken->getIsValid(), 'Expired token is valid!');
    }

    public function testSetTokenStoresUnderCustomTokenParamKey(): void
    {
        $oauthToken = new OAuthToken();
        $oauthToken->setTokenParamKey('custom_token_key');

        $oauthToken->setToken('abc123');

        $this->assertSame('abc123', $oauthToken->getParam('custom_token_key'));
        $this->assertNull($oauthToken->getParam('oauth_token'));
    }

    public function testGetExpireDurationParamKeyIsPubliclyCallable(): void
    {
        $oauthToken = new OAuthToken();

        $key = $oauthToken->getExpireDurationParamKey();

        $this->assertSame('expires_in', $key);
    }

    public function testGetExpireDurationParamKeyFindsCustomExpirationKeyInParams(): void
    {
        $oauthToken = new OAuthToken();
        $oauthToken->setParams(['access_token' => 'abc', 'custom_expiry' => 3600]);

        $key = $oauthToken->getExpireDurationParamKey();

        $this->assertSame('custom_expiry', $key);
    }

    /**
     * tokenSecretParamKey has no public setter and is never reassigned, so it's always the truthy
     * default 'oauth_token_secret' through the public API — meaning `?:` and an inverted `? :` are
     * indistinguishable via any reachable call. Reflection forces a different (still truthy) value to
     * prove getTokenSecret() actually reads the property rather than a hardcoded literal.
     */
    public function testGetTokenSecretUsesConfiguredParamKeyOverLiteralDefault(): void
    {
        $oauthToken = new OAuthToken();
        (new ReflectionProperty($oauthToken, 'tokenSecretParamKey'))->setValue($oauthToken, 'custom_secret_key');
        $oauthToken->setParam('custom_secret_key', 'shh');

        $this->assertSame('shh', $oauthToken->getTokenSecret());
    }

    /**
     * Same rationale as {@see testGetTokenSecretUsesConfiguredParamKeyOverLiteralDefault()}, for the
     * write side.
     */
    public function testSetTokenSecretUsesConfiguredParamKeyOverLiteralDefault(): void
    {
        $oauthToken = new OAuthToken();
        (new ReflectionProperty($oauthToken, 'tokenSecretParamKey'))->setValue($oauthToken, 'custom_secret_key');

        $oauthToken->setTokenSecret('shh');

        $this->assertSame('shh', $oauthToken->getParam('custom_secret_key'));
        $this->assertNull($oauthToken->getParam('oauth_token_secret'));
    }

    /**
     * getExpireDuration() is `mixed` and can return a non-numeric string (e.g. a malformed
     * "expires_in" from a provider response). The (int) cast turns that into 0 (immediately expired)
     * rather than letting it reach `+` in getIsExpired(), which throws a TypeError on a non-numeric
     * string operand.
     */
    public function testGetIsExpiredCastsNonNumericExpireDurationToZero(): void
    {
        $oauthToken = new OAuthToken();
        $oauthToken->setToken('abc123');
        $oauthToken->setParam('expires_in', 'not-a-number');

        $this->assertTrue($oauthToken->getIsExpired());
    }
}
