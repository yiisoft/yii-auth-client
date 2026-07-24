<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetLoader;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceStyleAsset;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestAuthChoiceItem;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
use Yiisoft\Yii\AuthClient\Widget\AuthChoiceItem;

final class AuthChoiceTest extends TestCase
{
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

    private function createAssetManager(): AssetManager
    {
        $aliases = new Aliases([
            '@vendor' => dirname(__DIR__, 2) . '/vendor',
            '@assets' => dirname(__DIR__, 2) . '/resources/assets',
            '@assetsUrl' => '/assets',
        ]);
        return new AssetManager($aliases, new AssetLoader($aliases));
    }

    /**
     * @param array<string, \Yiisoft\Yii\AuthClient\OAuth2> $clients
     */
    private function createWidgetWithDeps(array $clients, WebView $webView, AssetManager $assetManager, ?UrlGeneratorInterface $urlGenerator = null): AuthChoice
    {
        ob_start();
        try {
            return new AuthChoice(
                new Collection($clients),
                $urlGenerator ?? $this->createUrlGeneratorStub(),
                $webView,
                $assetManager,
            );
        } finally {
            ob_end_clean();
        }
    }

    private function getRegisteredJsScript(WebView $webView): ?string
    {
        $state = (new ReflectionProperty($webView, 'state'))->getValue($webView);
        $entries = $state->getJs()[WebView::POSITION_END] ?? [];

        return $entries === [] ? null : array_values($entries)[0];
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
        // Guards against re-introducing double-encoding: the client link markup produced by
        // clientLink() must appear as real nested tags, not HTML-escaped text inside <li>/<ul>.
        $this->assertStringContainsString(
            '<li><a class="test auth-link" title="Test" data-popup-width="860" data-popup-height="480" href="http://auth.local/callback">',
            $rendered,
        );
        $this->assertStringNotContainsString('&lt;', $rendered);
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

    /**
     * The auto-generated icon span is trusted, already-rendered HTML and must not be re-encoded (see
     * testClientLinkGeneratesSpanWithAuthIconAndClientNameClass), but an explicit, caller-supplied $text is
     * plain text and must still be HTML-encoded - otherwise this would be an XSS vector for any caller passing
     * through unsanitized input.
     */
    public function testClientLinkEncodesExplicitTextContainingHtmlSpecialCharacters(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client, '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
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

    public function testConstructorDoesNotEchoOrRegisterAnything(): void
    {
        $assetManager = $this->createAssetManager();

        ob_start();
        new AuthChoice(
            new Collection([]),
            $this->createUrlGeneratorStub(),
            new WebView(),
            $assetManager,
        );
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $this->assertFalse($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
    }

    /**
     * The bug this guards against: the constructor used to register assets and echo the opening `<div>` tag
     * immediately, before {@see popupMode()}/{@see options()}/{@see clientOptions()} could ever be called on
     * the returned instance - making them permanently unable to affect the output. begin()/render() must be
     * the ones producing this output instead, so calling those configuration methods first actually works.
     */
    public function testPopupModeOptionsAndClientOptionsTakeEffectWhenSetBeforeRender(): void
    {
        $webView = new WebView();
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], $webView, $assetManager)
            ->popupMode(false)
            ->options(['class' => 'custom-container'])
            ->clientOptions(['foo' => 'bar']);

        $rendered = $widget->render();

        $this->assertStringContainsString('class="custom-container"', $rendered);
        $this->assertTrue($assetManager->isRegisteredBundle(AuthChoiceStyleAsset::class));
        $this->assertFalse($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
        $this->assertNull($this->getRegisteredJsScript($webView));
    }

    public function testPopupModeReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->popupMode(false));
    }

    public function testOptionsReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->options([]));
    }

    public function testClientOptionsReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->clientOptions([]));
    }

    public function testBeginEchoesOpeningDivTagAndRegistersAssets(): void
    {
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], new WebView(), $assetManager);

        ob_start();
        $widget->begin();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div', $output);
        $this->assertStringContainsString('id="yii-auth-client"', $output);
        $this->assertTrue($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
    }

    /**
     * Exercises the real begin()/end() lifecycle (the base Widget stack). Two things must both hold:
     * - begin() must call parent::begin() to push onto the stack, otherwise the matching end() throws.
     * - renderOpenTag() must run at most once per widget: end() calls render(), which must not re-open
     *   (and so re-echo) the `<div>` a second time on top of what begin() already echoed.
     */
    public function testBeginThenEndOpensDivOnceAndClosesItOnce(): void
    {
        $widget = $this->createWidgetWithDeps([], new WebView(), $this->createAssetManager());

        ob_start();
        $widget->begin();
        $opened = ob_get_clean();
        $closed = AuthChoice::end();

        $this->assertStringContainsString('<div', $opened);
        $this->assertStringNotContainsString('<div', $closed);
        $this->assertStringContainsString('</div>', $closed);
    }

    public function testRenderRegistersAuthChoiceAssetInPopupMode(): void
    {
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], new WebView(), $assetManager);

        $widget->render();

        $this->assertTrue($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
    }

    public function testRenderRegistersJsWithClientIdAndAuthchoiceInvocation(): void
    {
        $webView = new WebView();
        $widget = $this->createWidgetWithDeps([], $webView, $this->createAssetManager());

        $widget->render();

        $js = $this->getRegisteredJsScript($webView);
        $this->assertNotNull($js);
        $this->assertStringContainsString("document.getElementById('yii-auth-client')", $js);
        $this->assertStringContainsString('authchoice(el, )', $js);
    }

    public function testRenderEncodesNonEmptyClientOptionsAsJsonForJsInvocation(): void
    {
        $webView = new WebView();
        $widget = $this->createWidgetWithDeps([], $webView, $this->createAssetManager())
            ->clientOptions(['foo' => 'bar']);

        $widget->render();

        $js = $this->getRegisteredJsScript($webView);
        $this->assertNotNull($js);
        $this->assertStringContainsString('authchoice(el, {"foo":"bar"})', $js);
    }

    public function testRenderRegistersStyleAssetWhenPopupModeDisabled(): void
    {
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], new WebView(), $assetManager)->popupMode(false);

        $widget->render();

        $this->assertTrue($assetManager->isRegisteredBundle(AuthChoiceStyleAsset::class));
    }

    public function testClientLinkThrowsWhenWidgetConfigMissingClassKey(): void
    {
        $client = $this->createTestClient();
        (new ReflectionProperty($client, 'viewOptions'))->setValue($client, ['widget' => ['someOption' => 'value']]);
        $widget = $this->createWidget(['test' => $client]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Widget config "class" parameter is missing');

        $widget->clientLink($client);
    }

    public function testClientLinkThrowsWhenWidgetClassIsNotAuthChoiceItemSubclass(): void
    {
        $client = $this->createTestClient();
        (new ReflectionProperty($client, 'viewOptions'))->setValue($client, ['widget' => ['class' => \stdClass::class]]);
        $widget = $this->createWidget(['test' => $client]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Item widget class must be subclass of "' . AuthChoiceItem::class . '"');

        $widget->clientLink($client);
    }

    public function testClientLinkRendersConfiguredWidgetClassWithClientAndAuthChoice(): void
    {
        $client = $this->createTestClient();
        // A plain object (not an array) here means clientLink() must actually apply the (array) cast
        // to reach ['class' => ...]: without the cast, isset($object['class']) throws a TypeError.
        (new ReflectionProperty($client, 'viewOptions'))
            ->setValue($client, ['widget' => (object) ['class' => TestAuthChoiceItem::class]]);
        $widget = $this->createWidget(['test' => $client]);

        $html = $widget->clientLink($client);

        $this->assertSame('auth-choice-item:test', $html);
    }
}
