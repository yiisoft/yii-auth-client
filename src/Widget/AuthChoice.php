<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widget;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\A;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceStyleAsset;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Override;

/**
 * AuthChoice prints buttons for authentication via various auth clients.
 * It opens a popup window for the client authentication process.
 * By default this widget relies on presence of {@see \Yiisoft\Yii\AuthClient\Collection} among application components
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
 * @see \Yiisoft\Yii\AuthClient\AuthAction
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
     *
     * @see Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    private array $options = [];
    /**
     * @var array additional options to be passed to the underlying JS plugin.
     */
    private array $clientOptions = [];
    /**
     * @var bool indicates if popup window should be used instead of direct links.
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
     * @var bool whether {@see renderOpenTag()} has already registered assets and produced the opening
     * `<div>` tag, so it is not done twice for a single {@see begin()}/{@see render()} pair.
     */
    private bool $openTagRendered = false;

    /** @var array<string, OAuth2> */
    private array $clients;

    public function __construct(
        Collection $clientCollection,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WebView $webView,
        private readonly AssetManager $assetManager,
    ) {
        $this->clients = $clientCollection->getClients();
    }

    /**
     * Opens the widget: registers assets and echoes the opening `<div>` tag, so that content written directly
     * to output between {@see begin()} and {@see end()} appears nested inside it.
     */
    #[Override]
    public function begin(): ?string
    {
        parent::begin();
        echo $this->renderOpenTag();
        return null;
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

            if (empty($this->clientOptions)) {
                $options = '';
            } else {
                $options = Json::htmlEncode($this->clientOptions);
            }

            $this->webView->registerJs("
                const el = document.getElementById('" . $this->getId() . "');
                if (el && typeof authchoice === 'function') {
                    authchoice(el, {$options});
                }
            ");
        } else {
            $this->assetManager->register(AuthChoiceStyleAsset::class);
        }

        $this->options['id'] = $this->getId();
        return Html::div('', $this->options)->open();
    }

    public function getId(): string
    {
        return 'yii-auth-client';
    }

    /**
     * Runs the widget.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     *
     * @return string rendered HTML.
     */
    #[Override]
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
     * Renders the main content, which includes all external services links.
     *
     * @throws InvalidConfigException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     *
     * @return string generated HTML.
     */
    protected function renderMainContent(): string
    {
        $items = [];
        /**
         * @var OAuth2 $externalService
         */
        foreach ($this->getClients() as $externalService) {
            // encode(false): clientLink() already returns rendered, safe-to-embed HTML.
            $items[] = Html::li($this->clientLink($externalService))->encode(false);
        }

        return Html::ul(['class' => 'auth-clients'])->items(...$items)->render();
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
            fn($client) => $client->getName() === $name
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

        if (empty($viewOptions['widget'])) {
            // Only the auto-generated icon markup below is already-rendered HTML; a caller-supplied $text
            // is plain text and must still be encoded, so the two cases need different `encode` settings.
            $encodeText = $text !== null;
            if ($text === null) {
                $text = Html::span('', ['class' => 'auth-icon ' . $client->getName()])->render();
            }
            if (!isset($htmlOptions['class'])) {
                $htmlOptions['class'] = $client->getName();
            }
            if (!isset($htmlOptions['title'])) {
                $htmlOptions['title'] = $client->getTitle();
            }
            Html::addCssClass($htmlOptions, ['widget' => 'auth-link']);

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

        $widgetConfig = (array)$viewOptions['widget'];
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
     * Composes client auth URL.
     *
     * @param AuthClientInterface $client external auth client instance.
     *
     * @return string auth URL.
     */
    public function createClientUrl($client): string
    {
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
     * Note: Popup window with {$authRoute} e.g. 'auth/authclient'
     * @param array $provider
     * @param string $name
     * @return string
     */
    public function authRoutedButtons(string $authRoute, array $provider, string $name): string
    {
        foreach ($this->getClients() as $client) {
            if ($name === $client->getName()) {
                if (strlen($client->getClientId()) > 0) {
                    $viewOptions = $client->getViewOptions();
                    /**
                     * @var int $viewOptions['popupHeight']
                     * @var int $viewOptions['popupWidth']
                     */
                    $height = $viewOptions['popupHeight'];
                    $width = $viewOptions['popupWidth'];
                    $this->authRoute($authRoute);
                    return $this->clientLink($client, ' ' . ucfirst((string) $provider['buttonName']), [
                        'onclick' => "window.open(this.href, 'authPopup', 'width=" . $width . ',height=' . $height . "'); return false;",
                        'class' => $client->getButtonClass() ,
                    ]);
                }
            }
        }
        return '';
    }

    /**
     * Note: No popup window and no route
     * @param ServerRequestInterface $request
     * @param array $provider
     * @param string $name
     * @return string
     */
    public function absoluteButtons(ServerRequestInterface $request, array $provider, string $name): string
    {
        foreach ($this->getClients() as $client) {
            if ($name === $client->getName()) {
                if (strlen($client->getClientId()) > 0) {
                    $clientAuthUrl = $client->buildAuthUrl($request, (array) $provider['params']);
                    return (new A())
                        ->addClass($client->getButtonClass())
                        ->content(' ' . ucfirst((string) $provider['buttonName']))
                        ->href($clientAuthUrl)
                        ->id('btn-' . $name)
                        ->render();
                }
            }
        }
        return '';
    }
}
