<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Yiisoft\Yii\AuthClient\Client\Facebook;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

final class FacebookTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(Facebook::class);
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('facebook', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Facebook', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-primary bi bi-facebook', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://graph.facebook.com/oauth/access_token', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('public_profile', $client->getScope());
    }

    public function testGetViewOptions(): void
    {
        $client = $this->createClient();

        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $client->getViewOptions());
    }

    public function testBuildAuthUrlUsesAuthorizeEndpoint(): void
    {
        $client = $this->createClient();
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub());

        $this->assertStringStartsWith('https://www.facebook.com/dialog/oauth?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testApplyAccessTokenToRequestAddsAppSecretProof(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('the-access-token', $params['access_token']);
        $this->assertSame(hash_hmac('sha256', 'the-access-token', 'the-client-secret'), $params['appsecret_proof']);
    }

    public function testApplyAccessTokenToRequestAddsMachineIdWhenPresent(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $token->setParam('machine_id', 'the-machine-id');
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('the-machine-id', $params['machine_id']);
    }

    /**
     * machine_id is cast to string before the `empty()` check. A negative-zero float is "empty" as
     * a raw value but not once cast to the string "-0", so this distinguishes the cast being applied.
     */
    public function testApplyAccessTokenToRequestCastsMachineIdToStringBeforeEmptyCheck(): void
    {
        $client = $this->createClient();
        $client->setClientSecret('the-client-secret');
        $token = new OAuthToken();
        $token->setToken('the-access-token');
        $token->setParam('machine_id', -0.0);
        $request = (new Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $params = \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest);
        $this->assertSame('-0', $params['machine_id']);
    }
}
