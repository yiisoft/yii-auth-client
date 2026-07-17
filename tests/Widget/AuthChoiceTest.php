<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetLoader;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;

final class AuthChoiceTest extends TestCase
{
    /**
     * @psalm-return UrlGeneratorInterface&\PHPUnit\Framework\MockObject\Stub
     */
    private function createUrlGeneratorStub(): UrlGeneratorInterface
    {
        return $this->createStub(UrlGeneratorInterface::class);
    }

    private function createTestClient(): TestClient
    {
        return new TestClient(
            $this->createStub(ClientInterface::class),
            $this->createStub(\Psr\Http\Message\RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }

    private function createTestClientWithoutClientId(): \Yiisoft\Yii\AuthClient\OAuth2
    {
        return new class (
            $this->createStub(ClientInterface::class),
            $this->createStub(\Psr\Http\Message\RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        ) extends \Yiisoft\Yii\AuthClient\OAuth2 {
            #[\Override]
            public function getName(): string
            {
                return 'test';
            }

            #[\Override]
            public function getTitle(): string
            {
                return 'Test';
            }

            #[\Override]
            public function getButtonClass(): string
            {
                return 'btn btn-primary bi';
            }

            #[\Override]
            public function getClientId(): string
            {
                return '';
            }

            #[\Override]
            public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
            {
                return 'http://test.local';
            }
        };
    }

    /**
     * @param array<string, \Yiisoft\Yii\AuthClient\OAuth2> $clients
     */
    private function createWidget(array $clients = [], ?UrlGeneratorInterface $urlGenerator = null): AuthChoice
    {
        $aliases = new Aliases([
            '@vendor' => dirname(__DIR__, 2) . '/vendor',
            '@assets' => dirname(__DIR__, 2) . '/resources/assets',
            '@assetsUrl' => '/assets',
        ]);
        ob_start();
        try {
            return new AuthChoice(
                new Collection($clients),
                $urlGenerator ?? $this->createUrlGeneratorStub(),
                new WebView(),
                new AssetManager($aliases, new AssetLoader($aliases)),
            );
        } finally {
            ob_end_clean();
        }
    }

    public function testGetIdIsFixed(): void
    {
        $widget = $this->createWidget();

        $this->assertSame('yii-auth-client', $widget->getId());
    }

    public function testGetClientsReturnsClientsFromCollection(): void
    {
        $client = $this->createTestClient();

        $widget = $this->createWidget(['test' => $client]);

        $this->assertSame(['test' => $client], $widget->getClients());
    }

    public function testSetClientsOverridesClients(): void
    {
        $widget = $this->createWidget();
        $client = $this->createTestClient();

        $widget->setClients(['test' => $client]);

        $this->assertSame(['test' => $client], $widget->getClients());
    }

    public function testGetClientReturnsMatchingClientByName(): void
    {
        $client = $this->createTestClient();
        $widget = $this->createWidget(['test' => $client]);

        $found = $widget->getClient('test');

        $this->assertSame($client, $found);
    }

    public function testGetClientThrowsForUnknownName(): void
    {
        $widget = $this->createWidget();

        $this->expectException(InvalidConfigException::class);

        $widget->getClient('unknown');
    }

    public function testCreateClientUrlUsesUrlGeneratorWithClientIdParam(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $name, array $arguments = []) => 'http://auth.local/' . $name . '?' . http_build_query($arguments)
        );
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $url = $widget->createClientUrl($client);

        $this->assertSame('http://auth.local/site/auth?authclient=test', $url);
    }

    public function testAuthRouteReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $result = $widget->authRoute('site/auth');

        $this->assertSame($widget, $result);
    }

    public function testClientLinkRendersAnchorWithExpectedAttributes(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('href="http://auth.local/callback"', $html);
        $this->assertStringContainsString('class="test auth-link"', $html);
        $this->assertStringContainsString('title="Test"', $html);
        $this->assertStringContainsString('data-popup-width="860"', $html);
        $this->assertStringContainsString('data-popup-height="480"', $html);
    }

    public function testAuthRoutedButtonsReturnsEmptyStringWhenClientNotFound(): void
    {
        $widget = $this->createWidget();

        $html = $widget->authRoutedButtons('site/auth', ['buttonName' => 'test'], 'unknown');

        $this->assertSame('', $html);
    }

    public function testAuthRoutedButtonsRendersLinkForMatchingClientWithClientId(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator);

        $html = $widget->authRoutedButtons('site/auth', ['buttonName' => 'test'], 'test');

        $this->assertStringContainsString('href="http://auth.local/callback"', $html);
        $this->assertStringContainsString('btn btn-primary bi', $html);
    }

    public function testAbsoluteButtonsReturnsEmptyStringWhenClientNotFound(): void
    {
        $widget = $this->createWidget();

        $html = $widget->absoluteButtons($this->createStub(ServerRequestInterface::class), ['params' => []], 'unknown');

        $this->assertSame('', $html);
    }

    public function testAbsoluteButtonsRendersLinkUsingClientBuildAuthUrl(): void
    {
        $client = $this->createTestClient();
        $widget = $this->createWidget(['test' => $client]);

        $html = $widget->absoluteButtons(
            $this->createStub(ServerRequestInterface::class),
            ['params' => [], 'buttonName' => 'test'],
            'test'
        );

        $this->assertStringContainsString('href="http://test.local"', $html);
        $this->assertStringContainsString('id="btn-test"', $html);
    }

    public function testInitIsPubliclyCallable(): void
    {
        $widget = $this->createWidget();

        ob_start();
        $widget->init();
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testGetClientPicksMatchingClientAmongMultiple(): void
    {
        $wanted = $this->createTestClient();
        $other = new class (
            $this->createStub(ClientInterface::class),
            $this->createStub(\Psr\Http\Message\RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        ) extends \Yiisoft\Yii\AuthClient\OAuth2 {
            #[\Override]
            public function getName(): string
            {
                return 'other';
            }

            #[\Override]
            public function getTitle(): string
            {
                return 'Other';
            }

            #[\Override]
            public function getButtonClass(): string
            {
                return 'btn';
            }

            #[\Override]
            public function getClientId(): string
            {
                return 'other-id';
            }

            #[\Override]
            public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
            {
                return 'http://other.local';
            }
        };
        // The matching client ("wanted") is placed first, "other" last: without the array_filter()
        // in getClient(), end() would wrongly return "other" instead.
        $widget = $this->createWidget(['wanted' => $wanted, 'other' => $other]);

        $found = $widget->getClient('test');

        $this->assertSame($wanted, $found);
    }

    public function testRenderIncludesMainContentWhenAutoRenderIsEnabled(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $rendered = $widget->render();

        $this->assertStringContainsString('auth-clients', $rendered);
        $this->assertStringContainsString('href="http://auth.local/callback"', $rendered);
        $this->assertStringContainsString('</div>', $rendered);
    }

    public function testClientLinkUsesExplicitTextInsteadOfGeneratedSpan(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client, 'Custom Text');

        $this->assertStringContainsString('>Custom Text<', $html);
        $this->assertStringNotContainsString('auth-icon', $html);
    }

    public function testClientLinkGeneratesSpanWithAuthIconAndClientNameClass(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('<span class="auth-icon test">', html_entity_decode($html));
    }

    public function testCreateClientUrlDisablesAutoRender(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $widget->createClientUrl($client);
        $rendered = $widget->render();

        $this->assertStringNotContainsString('auth-clients', $rendered);
    }

    public function testAuthRoutedButtonsReturnsEmptyStringForClientWithoutClientId(): void
    {
        $client = $this->createTestClientWithoutClientId();
        $widget = $this->createWidget(['test' => $client]);

        $html = $widget->authRoutedButtons('site/auth', ['buttonName' => 'test'], 'test');

        $this->assertSame('', $html);
    }

    public function testAuthRoutedButtonsSetsAuthRouteUsedByUrlGenerator(): void
    {
        $client = $this->createTestClient();
        $usedRouteName = null;
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturnCallback(
            function (string $name) use (&$usedRouteName): string {
                $usedRouteName = $name;
                return 'http://auth.local/callback';
            }
        );
        $widget = $this->createWidget(['test' => $client], $urlGenerator);

        $widget->authRoutedButtons('site/custom-auth-route', ['buttonName' => 'test'], 'test');

        $this->assertSame('site/custom-auth-route', $usedRouteName);
    }

    public function testAuthRoutedButtonsCapitalizesButtonNameWithLeadingSpace(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator);

        $html = $widget->authRoutedButtons('site/auth', ['buttonName' => 'login'], 'test');

        $this->assertStringContainsString('> Login<', $html);
    }

    public function testAuthRoutedButtonsIncludesOnclickWithPopupDimensions(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator);

        $html = $widget->authRoutedButtons('site/auth', ['buttonName' => 'test'], 'test');

        $this->assertStringContainsString(
            "window.open(this.href, 'authPopup', 'width=860,height=480'); return false;",
            str_replace('&apos;', "'", $html)
        );
    }

    public function testAbsoluteButtonsReturnsEmptyStringForClientWithoutClientId(): void
    {
        $client = $this->createTestClientWithoutClientId();
        $widget = $this->createWidget(['test' => $client]);

        $html = $widget->absoluteButtons(
            $this->createStub(ServerRequestInterface::class),
            ['params' => [], 'buttonName' => 'test'],
            'test'
        );

        $this->assertSame('', $html);
    }

    public function testAbsoluteButtonsCapitalizesButtonNameWithLeadingSpace(): void
    {
        $client = $this->createTestClient();
        $widget = $this->createWidget(['test' => $client]);

        $html = $widget->absoluteButtons(
            $this->createStub(ServerRequestInterface::class),
            ['params' => [], 'buttonName' => 'login'],
            'test'
        );

        $this->assertStringContainsString('> Login<', $html);
    }
}
