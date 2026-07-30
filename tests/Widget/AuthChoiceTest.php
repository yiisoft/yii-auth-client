<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use stdClass;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetLoader;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset;
use Yiisoft\Yii\AuthClient\Client\Google;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestAuthChoiceItem;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
use Yiisoft\Yii\AuthClient\Widget\AuthChoiceDisplayMode;
use Yiisoft\Yii\AuthClient\Widget\AuthChoiceItem;

use function dirname;

final class AuthChoiceTest extends TestCase
{
    public function testGetClientsReturnsClientsFromCollection(): void
    {
        $client = $this->createTestClient();

        $widget = $this->createWidget(['test' => $client]);

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
            fn(string $name, array $arguments = []) => 'http://auth.local/' . $name . '?' . http_build_query($arguments),
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
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')->popupMode(true);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('href="http://auth.local/callback"', $html);
        $this->assertStringContainsString('class="auth-link btn btn-primary"', $html);
        $this->assertStringContainsString('title="Test"', $html);
        $this->assertStringContainsString('data-popup-width="860"', $html);
        $this->assertStringContainsString('data-popup-height="480"', $html);
    }

    public function testGetClientPicksMatchingClientAmongMultiple(): void
    {
        $wanted = $this->createTestClient();
        $other = new class (
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        ) extends OAuth2 {
            public function getName(): string
            {
                return 'other';
            }

            public function getTitle(): string
            {
                return 'Other';
            }

            public function getButtonClass(): string
            {
                return 'btn';
            }

            public function getClientId(): string
            {
                return 'other-id';
            }

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
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')->popupMode(true);

        $rendered = $widget->render();

        $this->assertStringContainsString('btn-group', $rendered);
        $this->assertStringContainsString('href="http://auth.local/callback"', $rendered);
        $this->assertStringContainsString('</div>', $rendered);
        // Guards against re-introducing double-encoding: the client link markup produced by
        // clientLink() must appear as real nested tags, not HTML-escaped text.
        $this->assertStringNotContainsString('&lt;', $rendered);
        $this->assertStringNotContainsString('<ul>', $rendered);
        $this->assertStringNotContainsString('<li>', $rendered);
        // Verify link attributes without depending on attribute order
        $this->assertStringContainsString('class="auth-link btn btn-primary"', $rendered);
        $this->assertStringContainsString('title="Test"', $rendered);
        $this->assertStringContainsString('data-popup-width="860"', $rendered);
        $this->assertStringContainsString('data-popup-height="480"', $rendered);
        $this->assertStringContainsString('href="http://auth.local/callback"', $rendered);
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
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('auth-icon', html_entity_decode($html));
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', html_entity_decode($html));
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

    public function testConstructorDoesNotEchoOrRegisterAnything(): void
    {
        $assetManager = $this->createAssetManager();

        ob_start();
        new AuthChoice(
            new Collection([]),
            $this->createUrlGeneratorStub(),
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
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], $assetManager)
            ->popupMode(false)
            ->options(['class' => 'custom-container'])
            ->clientOptions(['foo' => 'bar']);

        $rendered = $widget->render();

        $this->assertStringContainsString('class="custom-container"', $rendered);
        $this->assertFalse($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
        $this->assertStringNotContainsString('data-authchoice', $rendered);
    }

    public function testPopupModeReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->popupMode(false));
    }

    public function testOptionsReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->options(['class' => 'custom-class']));
    }

    public function testOptionsAppliedToContainer(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->options(['class' => 'my-container', 'data-test' => 'value']);

        $html = $widget->render();

        $this->assertStringContainsString('class="my-container"', $html);
        $this->assertStringContainsString('data-test="value"', $html);
    }

    public function testClientOptionsReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->clientOptions([]));
    }

    public function testClientOptionsPassedToDataAttributeForAssetAutoInit(): void
    {
        $widget = $this->createWidgetWithDeps([], $this->createAssetManager())
            ->popupMode(true)
            ->clientOptions(['triggerSelector' => '.my-link']);

        $rendered = $widget->render();

        $this->assertStringContainsString('triggerSelector', $rendered);
    }

    public function testLinkAttributesReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->linkAttributes(['class' => 'btn btn-outline-secondary']));
    }

    public function testLinkAttributesAppliedToGeneratedLinks(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->linkAttributes(['class' => 'btn btn-outline-secondary']);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('class="auth-link btn btn-outline-secondary"', $html);
    }

    public function testLinkAttributesWithNonClassKeyAppliedAsIs(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->linkAttributes(['data-test' => 'value']);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('data-test="value"', $html);
    }

    public function testIconAttributesReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->iconAttributes(['style' => 'width:24px;height:24px;']));
    }

    public function testIconAttributesAppliedToSvgIcons(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->iconAttributes(['style' => 'width:24px;height:24px;']);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('auth-icon', html_entity_decode($html));
        $this->assertStringContainsString('style="width:24px;height:24px;"', html_entity_decode($html));
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', html_entity_decode($html));
    }

    public function testIconWidthReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->iconWidth('32'));
    }

    public function testIconWidthSetToNullOmitsWidthAttribute(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->iconWidth(null);

        $html = $widget->clientLink($client);

        $this->assertStringNotContainsString('width=', html_entity_decode($html));
        $this->assertStringContainsString('height="24"', html_entity_decode($html));
    }

    public function testIconHeightReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->iconHeight('32'));
    }

    public function testIconHeightSetToNullOmitsHeightAttribute(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->iconHeight(null);

        $html = $widget->clientLink($client);

        $this->assertStringNotContainsString('height=', html_entity_decode($html));
        $this->assertStringContainsString('width="24"', html_entity_decode($html));
    }

    public function testRenderClientLogoIncludesViewBoxForRegistryLogo(): void
    {
        $client = new Google(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['google' => $client], $urlGenerator)->authRoute('site/auth');

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('viewBox="0 0 268.152 273.883"', html_entity_decode($html));
    }

    public function testDisplayModeReturnsSelfForChaining(): void
    {
        $widget = $this->createWidget();

        $this->assertSame($widget, $widget->displayMode(AuthChoiceDisplayMode::Both));
    }

    public function testDisplayModeIconOnly(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->displayMode(AuthChoiceDisplayMode::Icon);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('<svg', html_entity_decode($html));
        $this->assertStringContainsString('</svg></a>', html_entity_decode($html));
    }

    public function testDisplayModeTextOnly(): void
    {
        $client = $this->createTestClient();
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->displayMode(AuthChoiceDisplayMode::Text);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('>Test<', $html);
        $this->assertStringNotContainsString('<svg', html_entity_decode($html));
    }

    public function testDisplayModeBoth(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')
            ->displayMode(AuthChoiceDisplayMode::Both);

        $html = $widget->clientLink($client);

        $this->assertStringContainsString('<svg', html_entity_decode($html));
        $this->assertStringContainsString('>Test<', $html);
        $this->assertStringContainsString('auth-title', $html);
    }

    public function testBeginEchoesOpeningDivTagAndRegistersAssets(): void
    {
        $assetManager = $this->createAssetManager();
        $widget = $this->createWidgetWithDeps([], $assetManager)->popupMode(true);

        ob_start();
        $widget->begin();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div', $output);
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
        $widget = $this->createWidgetWithDeps([], $this->createAssetManager());

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
        $widget = $this->createWidgetWithDeps([], $assetManager)->popupMode(true);

        $widget->render();

        $this->assertTrue($assetManager->isRegisteredBundle(AuthChoiceAsset::class));
    }

    public function testRenderSetsDataAuthchoiceAttributeInPopupMode(): void
    {
        $widget = $this->createWidgetWithDeps([], $this->createAssetManager())->popupMode(true);

        $rendered = $widget->render();

        $this->assertStringContainsString('data-authchoice', $rendered);
    }

    public function testRenderEncodesNonEmptyClientOptionsInDataAttribute(): void
    {
        $widget = $this->createWidgetWithDeps([], $this->createAssetManager())
            ->popupMode(true)
            ->clientOptions(['foo' => 'bar']);

        $rendered = $widget->render();

        $this->assertStringContainsString('data-authchoice', $rendered);
        $this->assertStringContainsString('foo', html_entity_decode($rendered));
        $this->assertStringContainsString('bar', html_entity_decode($rendered));
    }

    public function testRenderRegistersStyleAssetWhenPopupModeDisabled(): void
    {
        $client = $this->createTestClient();
        $client->setLogo('<circle cx="12" cy="12" r="10" fill="blue"/>');
        $urlGenerator = $this->createUrlGeneratorStub();
        $urlGenerator->method('generate')->willReturn('http://auth.local/callback');
        $widget = $this->createWidget(['test' => $client], $urlGenerator)->authRoute('site/auth')->popupMode(false);

        $rendered = $widget->render();

        // Icons are rendered as inline SVG with proper namespaces for xlink support
        $this->assertStringContainsString('auth-icon', $rendered);
        $this->assertStringContainsString('xmlns:xlink="http://www.w3.org/1999/xlink"', $rendered);
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
        (new ReflectionProperty($client, 'viewOptions'))->setValue($client, ['widget' => ['class' => stdClass::class]]);
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

    private function createUrlGeneratorStub(): UrlGeneratorInterface
    {
        return $this->createStub(UrlGeneratorInterface::class);
    }

    private function createTestClient(): TestClient
    {
        return new TestClient(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        );
    }

    private function createTestClientWithoutClientId(): OAuth2
    {
        return new class (
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            new DummyStateStorage(),
            new YiisoftFactory(),
            new Session(),
        ) extends OAuth2 {
            public function getName(): string
            {
                return 'test';
            }

            public function getTitle(): string
            {
                return 'Test';
            }

            public function getButtonClass(): string
            {
                return 'btn btn-primary bi';
            }

            public function getClientId(): string
            {
                return '';
            }

            public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params = []): string
            {
                return 'http://test.local';
            }
        };
    }

    /**
     * @param array<string, OAuth2> $clients
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
     * @param array<string, OAuth2> $clients
     */
    private function createWidgetWithDeps(array $clients, AssetManager $assetManager, ?UrlGeneratorInterface $urlGenerator = null): AuthChoice
    {
        ob_start();
        try {
            return new AuthChoice(
                new Collection($clients),
                $urlGenerator ?? $this->createUrlGeneratorStub(),
                $assetManager,
            );
        } finally {
            ob_end_clean();
        }
    }
}
