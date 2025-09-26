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
 * ?>
 * <?php $authChoice = AuthChoice::begin([
 *     'baseAuthUrl' => ['site/auth']
 * ]); ?>
 * <ul>
 * <?php foreach ($authChoice->getClients() as $client): ?>
 *     <li><?= $authChoice->clientLink($client) ?></li>
 * <?php endforeach; ?>
 * </ul>
 * <?php AuthChoice::end(); ?>
 * ```
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
    
    private array $clients;

    public function __construct(
        Collection $clientCollection,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WebView $webView,
        private readonly AssetManager $assetManager,
    ) {
        $this->clients = $clientCollection->getClients();
        $this->init();
    }

    /**
     * Initializes the widget.
     */
    public function init(): void
    {
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
        // This next line can cause header related issues
        echo Html::tag('div', '', $this->options)->open();
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
    #[\Override]
    public function render(): string
    {
        $content = '';
        if ($this->autoRender) {
            $content .= $this->renderMainContent();
        }
        $content .= Html::tag('div')->close();
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
            $items[] = Html::tag('li', $this->clientLink($externalService));
        }

        return Html::tag('ul', implode('', $items), ['class' => 'auth-clients'])->render();
    }

    /**
     * @return array
     * @psalm-suppress MixedReturnTypeCoercion
     * @psalm-return array<string, OAuth2>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @param OAuth2[] $clients
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
    public function clientLink(OAuth2 $client, string $text = null, array $htmlOptions = []): string
    {
        $viewOptions = $client->getViewOptions();

        if (empty($viewOptions['widget'])) {
            if ($text === null) {
                $text = Html::tag('span', '', ['class' => 'auth-icon ' . $client->getName()])->render();
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

            return Html::a($text, $this->createClientUrl($client), $htmlOptions)->render();
        }

        $widgetConfig = (array)$viewOptions['widget'];
        if (!isset($widgetConfig['class'])) {
            throw new InvalidConfigException('Widget config "class" parameter is missing');
        }
        /* @var $widgetClass Widget */
        $widgetClass = $widgetConfig['class'];
        /**
         * @psalm-suppress MixedArgument $widgetClass
         */
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
                    $height = (string) $viewOptions['popupHeight'];
                    $width = (string) $viewOptions['popupWidth'];
                    $this->authRoute($authRoute);
                    return $this->clientLink($client, ' ' . ucfirst((string) $provider['buttonName']), [
                        'onclick' => "window.open(this.href, 'authPopup', 'width=". $width . ",height=" . $height . "'); return false;",
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
                    return A::tag()
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