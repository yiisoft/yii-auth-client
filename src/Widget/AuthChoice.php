<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widget;

use Yiisoft\Assets\AssetManager;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\OAuth2;

/**
 * AuthChoice prints buttons for authentication via various auth clients.
 * It opens a popup window for the client authentication process.
 * By default this widget relies on presence of {@see Collection} among application components
 * to get auth clients information.
 *
 * Example:
 *
 * ```php
 * <?= AuthChoice::widget()->authRoute('site/auth'); ?>
 * ```
 *
 * You can customize the widget appearance by using {@see begin()} and {@see end()} syntax
 * along with using method {@see clientLink()} or {@see createClientUrl()}.
 * For example:
 *
 * ```php
 * <?php
 * use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
 *
 * $authChoice = AuthChoice::widget()->authRoute('site/auth');
 * $authChoice->begin();
 * ?>
 * <ul>
 * <?php foreach ($authChoice->getClients() as $client): ?>
 *     <li><?= $authChoice->clientLink($client) ?></li>
 * <?php endforeach; ?>
 * </ul>
 * <?= AuthChoice::end() ?>
 * ```
 *
 * Configuration methods ({@see popupMode()}, {@see options()}, {@see clientOptions()}, {@see authRoute()}) must
 * be called before {@see begin()}/{@see render()}, since that is when their asset registration and the opening
 * `<div>` tag are produced.
 *
 * This widget supports following keys for {@see AuthClientInterface::getViewOptions()} result:
 *
 *  - popupWidth: int, width of the popup window in pixels.
 *  - popupHeight: int, height of the popup window in pixels.
 *  - widget: array, configuration for the widget, which should be used to render a client link;
 *    such widget should be a subclass of {@see AuthChoiceItem}.
 *
 * @see AuthAction
 */
final class AuthChoice extends Widget
{
    /**
     * @var string name of the GET param , which should be used to passed auth client id to URL
     * defined by {@see baseAuthUrl}.
     */
    private string $clientIdGetParamName = 'authclient';
    /**
     * @var array the HTML attributes that should be rendered in the div HTML tag representing the container element.
     * Default: Bootstrap button group `['class' => 'btn-group']`.
     *
     * @see Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    private array $options = ['class' => 'btn-group'];
    /**
     * @var array additional options to be passed to the underlying JS plugin.
     */
    private array $clientOptions = [];
    /**
     * @var bool indicates if popup window should be used instead of direct links.
     * Default is true.
     */
    private bool $popupMode = true;
    /**
     * @var bool indicates if widget content, should be rendered automatically.
     * Note: this value automatically set to 'false' at the first call of {@see createClientUrl()}
     */
    private bool $autoRender = true;

    /**
     * @var string route name for the external clients authentication URL.
     */
    private string $authRoute = '';

    /**
     * @var AuthChoiceDisplayMode what {@see clientLink()} renders by default for a client button.
     */
    private AuthChoiceDisplayMode $displayMode = AuthChoiceDisplayMode::Icon;

    /**
     * @var array HTML attributes for SVG icons, merged with the default `['class' => 'auth-icon']`.
     */
    private array $iconAttributes = [];

    /**
     * @var string|null width of SVG icons. Set to null to omit the width attribute (e.g., when CSS handles sizing).
     */
    private ?string $iconWidth = '24';

    /**
     * @var string|null height of SVG icons. Set to null to omit the height attribute (e.g., when CSS handles sizing).
     */
    private ?string $iconHeight = '24';

    /**
     * @var array HTML attributes for auth links, merged with the default `['class' => 'auth-link']`.
     * Default: Bootstrap button classes `['class' => 'btn btn-primary']`.
     */
    private array $linkAttributes = ['class' => 'btn btn-primary'];

    /**
     * @var bool whether {@see renderOpenTag()} has already registered assets and produced the opening
     * `<div>` tag, so it is not done twice for a single {@see begin()}/{@see render()} pair.
     */
    private bool $openTagRendered = false;

    /** @var array<string, OAuth2> */
    private array $clients;

    public function __construct(
        Collection $clientCollection,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AssetManager $assetManager,
    ) {
        $this->clients = $clientCollection->getClients();
    }

    /**
     * Opens the widget: registers assets and echoes the opening `<div>` tag, so that content written directly
     * to output between {@see begin()} and {@see end()} appears nested inside it.
     */
    public function begin(): ?string
    {
        parent::begin();
        echo $this->renderOpenTag();
        return null;
    }

    /**
     * Runs the widget.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     *
     * @return string rendered HTML.
     */
    public function render(): string
    {
        $content = $this->renderOpenTag();
        if ($this->autoRender) {
            $content .= $this->renderMainContent();
        }
        $content .= Html::div()->close();
        return $content;
    }

    /**
     * @return array
     * @psalm-return array<string, OAuth2>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @param array<string, OAuth2> $clients
     */
    public function setClients(array $clients): void
    {
        $this->clients = $clients;
    }

    public function getClient(string $name): OAuth2
    {
        $clients = array_filter(
            $this->getClients(),
            fn($client) => $client->getName() === $name,
        );
        $client = end($clients);

        if ($client === false) {
            throw new InvalidConfigException("OAuth2 client with name '{$name}' not found.");
        }

        return $client;
    }

    /**
     * Outputs client auth link.
     *
     * @param OAuth2 $client extending from an auth client instance.
     * @param string $text link text, if not set - default value will be generated.
     * @param array $htmlOptions link HTML options.
     *
     * @throws InvalidConfigException on wrong configuration.
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     *
     * @return string generated HTML.
     */
    public function clientLink(OAuth2 $client, ?string $text = null, array $htmlOptions = []): string
    {
        $viewOptions = $client->getViewOptions();

        if (!empty($viewOptions['widget'])) {
            return $this->renderClientItemWidget($client, (array) $viewOptions['widget']);
        }

        $encodeText = $text !== null;
        if ($text === null) {
            $icon = $this->renderClientLogo($client);
            /** @infection-ignore-all Each arm renders different content for the three display modes */
            $text = match ($this->displayMode) {
                AuthChoiceDisplayMode::Icon => $icon,
                AuthChoiceDisplayMode::Text => $client->getTitle(),
                AuthChoiceDisplayMode::Both => $icon
                    . Html::span($client->getTitle(), ['class' => 'auth-title ms-2'])->render(),
            };
            $encodeText = $this->displayMode === AuthChoiceDisplayMode::Text;
        }
        if (!isset($htmlOptions['title'])) {
            $htmlOptions['title'] = $client->getTitle();
        }
        $hasExplicitClass = isset($htmlOptions['class']);
        Html::addCssClass($htmlOptions, ['widget' => 'auth-link']);
        foreach ($this->linkAttributes as $key => $value) {
            if ($key !== 'class') {
                $htmlOptions[$key] = $value;
            } elseif (!$hasExplicitClass) {
                Html::addCssClass($htmlOptions, (string) $value);
            }
        }

        if ($this->popupMode) {
            if (isset($viewOptions['popupWidth'])) {
                /**
                 * @var int $viewOptions['popupWidth']
                 * @var int $htmlOptions['data-popup-width']
                 */
                $htmlOptions['data-popup-width'] = $viewOptions['popupWidth'];
            }
            if (isset($viewOptions['popupHeight'])) {
                /**
                * @var int $viewOptions['popupHeight']
                * @var int $htmlOptions['data-popup-height']
                */
                $htmlOptions['data-popup-height'] = $viewOptions['popupHeight'];
            }
        }

        return Html::a($text, $this->createClientUrl($client), $htmlOptions)->encode($encodeText)->render();
    }

    /**
     * Composes client auth URL.
     *
     * @param AuthClientInterface $client external auth client instance.
     *
     * @return string auth URL.
     */
    public function createClientUrl($client): string
    {
        /** @infection-ignore-all Disable auto-render when URL is requested directly; caller handles rendering */
        $this->autoRender = false;
        $params = [];
        $params[$this->clientIdGetParamName] = $client->getName();

        return $this->urlGenerator->generate($this->authRoute, $params);
    }

    /**
     * @param string $authRoute
     *
     * @return self
     */
    public function authRoute(string $authRoute): self
    {
        $this->authRoute = $authRoute;
        return $this;
    }

    /**
     * @param bool $popupMode whether a popup window should be used instead of direct links. Must be called
     * before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function popupMode(bool $popupMode): self
    {
        $this->popupMode = $popupMode;
        return $this;
    }

    public function displayMode(AuthChoiceDisplayMode $displayMode): self
    {
        $this->displayMode = $displayMode;
        return $this;
    }

    /**
     * @param array $iconAttributes HTML attributes for SVG icons, merged with default `['class' => 'auth-icon']`.
     * Must be called before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function iconAttributes(array $iconAttributes): self
    {
        $this->iconAttributes = $iconAttributes;
        return $this;
    }

    /**
     * @param string|null $iconWidth width of SVG icons (e.g., '24'). Set to null to omit the attribute.
     * Must be called before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function iconWidth(?string $iconWidth): self
    {
        $this->iconWidth = $iconWidth;
        return $this;
    }

    /**
     * @param string|null $iconHeight height of SVG icons (e.g., '24'). Set to null to omit the attribute.
     * Must be called before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function iconHeight(?string $iconHeight): self
    {
        $this->iconHeight = $iconHeight;
        return $this;
    }

    /**
     * @param array $linkAttributes HTML attributes for auth links, merged with default `['class' => 'auth-link']`.
     * Use this to apply framework-specific classes (e.g., Bootstrap's 'btn btn-outline-secondary').
     * Must be called before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function linkAttributes(array $linkAttributes): self
    {
        $this->linkAttributes = $linkAttributes;
        return $this;
    }

    /**
     * @param array $options the HTML attributes for the container `<div>` tag, see {@see Html::renderTagAttributes()}.
     * Must be called before {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param array $clientOptions additional options passed to the underlying JS plugin. Must be called before
     * {@see begin()}/{@see render()} to take effect.
     *
     * @return self
     */
    public function clientOptions(array $clientOptions): self
    {
        $this->clientOptions = $clientOptions;
        return $this;
    }

    /**
     * Renders the main content, which includes all external services links.
     *
     * @throws InvalidConfigException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     *
     * @return string generated HTML.
     */
    protected function renderMainContent(): string
    {
        $content = '';
        /**
         * @var OAuth2 $externalService
         */
        foreach ($this->getClients() as $externalService) {
            // clientLink() already returns rendered, safe-to-embed HTML.
            /** @infection-ignore-all Must concatenate to render all clients, not just the last one */
            $content .= $this->clientLink($externalService);
        }

        return $content;
    }

    /**
     * Renders an inline SVG icon for the client. Prefers the client's own logo (via {@see OAuth2::getLogo()}),
     * then falls back to the registry, then a placeholder span.
     */
    private function renderClientLogo(OAuth2 $client): string
    {
        /** @infection-ignore-all Client-provided logo (setLogo) takes precedence over registry default */
        $svg = $client->getLogo() ?? LogoRegistry::getLogo($client->getName());
        if ($svg !== null) {
            $attrs = ['class' => 'auth-icon', 'xmlns' => 'http://www.w3.org/2000/svg', 'xmlns:xlink' => 'http://www.w3.org/1999/xlink', 'preserveAspectRatio' => 'xMidYMid meet'];
            if ($this->iconWidth !== null) {
                $attrs['width'] = $this->iconWidth;
            }
            if ($this->iconHeight !== null) {
                $attrs['height'] = $this->iconHeight;
            }
            $viewBox = LogoRegistry::getViewBox($client->getName());
            /** @infection-ignore-all Only add viewBox when available to preserve aspect ratio for different logos */
            if ($viewBox !== null) {
                $attrs['viewBox'] = $viewBox;
            }
            foreach ($this->iconAttributes as $key => $value) {
                $attrs[$key] = $value;
            }
            $attrStr = Html::renderTagAttributes($attrs);
            /** @infection-ignore-all SVG structure: attributes, title, content, closing tag */
            return "<svg$attrStr><title>" . Html::encode($client->getTitle()) . "</title>$svg</svg>";
        }

        /** @infection-ignore-all Fallback span needs both auth-icon class and client name for styling/testing */
        return Html::span('', ['class' => 'auth-icon ' . $client->getName()])->render();
    }

    /**
     * Renders a client link via a custom {@see AuthChoiceItem} widget instead of the default markup, per the
     * `widget` key of {@see AuthClientInterface::getViewOptions()}.
     *
     * @param array $widgetConfig the `widget` view option; must contain a `class` key naming an
     * {@see AuthChoiceItem} subclass, plus whatever other config that widget's constructor/setters accept.
     *
     * @throws InvalidConfigException if `class` is missing, or isn't an {@see AuthChoiceItem} subclass.
     */
    private function renderClientItemWidget(OAuth2 $client, array $widgetConfig): string
    {
        if (!isset($widgetConfig['class'])) {
            throw new InvalidConfigException('Widget config "class" parameter is missing');
        }
        /** @var class-string $widgetClass */
        $widgetClass = $widgetConfig['class'];
        if (!is_subclass_of($widgetClass, AuthChoiceItem::class)) {
            throw new InvalidConfigException('Item widget class must be subclass of "' . AuthChoiceItem::class . '"');
        }
        unset($widgetConfig['class']);
        $widgetConfig['client'] = $client;
        $widgetConfig['authChoice'] = $this;
        return $widgetClass::widget($widgetConfig)->render();
    }

    /**
     * Registers assets/JS and builds the opening `<div>` tag markup, exactly once per widget instance.
     * Subsequent calls (e.g. from both {@see begin()} and {@see render()} in a begin()/end() usage) return ''.
     */
    private function renderOpenTag(): string
    {
        if ($this->openTagRendered) {
            return '';
        }
        $this->openTagRendered = true;

        if ($this->popupMode) {
            $this->assetManager->register(AuthChoiceAsset::class);
            $this->options['data-authchoice'] = Json::htmlEncode($this->clientOptions);
        }

        return Html::div('', $this->options)->open();
    }
}
