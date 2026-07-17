<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Yiisoft\Yii\AuthClient\Client\Yandex;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;

final class YandexTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(Yandex::class);
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('yandex', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('Yandex', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-dark bi', $client->getButtonClass());
    }

    public function testGetTokenUrl(): void
    {
        $client = $this->createClient();

        $this->assertSame('https://oauth.yandex.com/token', $client->getTokenUrl());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('login:info', $client->getScope());
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

        $this->assertStringStartsWith('https://oauth.yandex.com/authorize?', $authUrl);
        $this->assertStringContainsString('client_id=client-id', $authUrl);
    }

    public function testApplyAccessTokenToRequestAddsOauthTokenAndFormat(): void
    {
        $client = $this->createClient();
        $token = new OAuthToken();
        $token->setToken('the-token');
        $request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createRequest('GET', 'http://example.com/');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $this->assertSame(
            ['format' => 'json', 'oauth_token' => 'the-token'],
            \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest)
        );
    }

    public function testApplyAccessTokenToRequestKeepsExistingFormat(): void
    {
        $client = $this->createClient();
        $token = new OAuthToken();
        $token->setToken('the-token');
        $request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createRequest('GET', 'http://example.com/?format=xml');

        $newRequest = $client->applyAccessTokenToRequest($request, $token);

        $this->assertSame(
            ['format' => 'xml', 'oauth_token' => 'the-token'],
            \Yiisoft\Yii\AuthClient\RequestUtil::getParams($newRequest)
        );
    }
}
