<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Yiisoft\Yii\AuthClient\Client\MicrosoftOnline;
use Yiisoft\Yii\AuthClient\OAuth2;

final class MicrosoftOnlineTest extends ProviderClientTestCase
{
    #[\Override]
    protected function createClient(): OAuth2
    {
        return $this->instantiate(MicrosoftOnline::class);
    }

    private function createMicrosoftClient(): MicrosoftOnline
    {
        return $this->instantiate(MicrosoftOnline::class);
    }

    public function testGetName(): void
    {
        $client = $this->createClient();

        $this->assertSame('microsoftonline', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient();

        $this->assertSame('MicrosoftOnline', $client->getTitle());
    }

    public function testGetButtonClass(): void
    {
        $client = $this->createClient();

        $this->assertSame('btn btn-warning bi bi-microsoft', $client->getButtonClass());
    }

    public function testGetDefaultScope(): void
    {
        $client = $this->createClient();

        $this->assertSame('offline_access User.Read', $client->getScope());
    }

    public function testGetViewOptions(): void
    {
        $client = $this->createClient();

        $this->assertSame(['popupWidth' => 860, 'popupHeight' => 480], $client->getViewOptions());
    }

    public function testDefaultTenantIsCommon(): void
    {
        $client = $this->createMicrosoftClient();

        $this->assertSame('common', $client->getTenant());
    }

    public function testSetTenantChangesTenant(): void
    {
        $client = $this->createMicrosoftClient();

        $client->setTenant('contoso');

        $this->assertSame('contoso', $client->getTenant());
    }

    public function testGetAuthUrlWithTenantInserted(): void
    {
        $client = $this->createMicrosoftClient();

        $authUrl = $client->getAuthUrlWithTenantInserted('contoso');

        $this->assertSame('https://login.microsoftonline.com/contoso/oauth2/v2.0/authorize', $authUrl);
    }

    public function testGetTokenUrlWithTenantInserted(): void
    {
        $client = $this->createMicrosoftClient();

        $tokenUrl = $client->getTokenUrlWithTenantInserted('contoso');

        $this->assertSame('https://login.microsoftonline.com/contoso/oauth2/v2.0/token', $tokenUrl);
    }

    public function testBuildAuthUrlRequiresExplicitTenantSubstitution(): void
    {
        $client = $this->createMicrosoftClient();
        $client->setClientId('client-id');
        $client->setOauth2ReturnUrl('http://return.local');
        $client->setAuthUrl($client->getAuthUrlWithTenantInserted($client->getTenant()));

        $authUrl = $client->buildAuthUrl($this->createServerRequestStub());

        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $authUrl);
    }
}
