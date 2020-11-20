<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset;
use Yiisoft\Yii\AuthClient\Asset\AuthChoiceStyleAsset;
use Yiisoft\Yii\AuthClient\ClientInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;

/**
 * AuthChoice prints buttons for authentication via various auth clients.
 * It opens a popup window for the client authentication process.
 * By default this widget relies on presence of {@see \Yiisoft\Yii\AuthClient\Collection} among application components
 * to get auth clients information.
 *
 * Example:
 *
 * ```php
 * <?= Yiisoft\Yii\AuthClient\Widgets\AuthChoice::widget([
 *     'baseAuthUrl' => ['site/auth']
 * ]); ?>
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
 * <?php $authAuthChoice = AuthChoice::begin([
 *     'baseAuthUrl' => ['site/auth']
 * ]); ?>
 * <ul>
 * <?php foreach ($authAuthChoice->getClients() as $client): ?>
 *     <li><?= $authAuthChoice->clientLink($client) ?></li>
 * <?php endforeach; ?>
 * </ul>
 * <?php AuthChoice::end(); ?>
 * ```
 *
 * This widget supports following keys for {@see ClientInterface::getViewOptions()} result:
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
     * @var Collection auth clients collection.
     */
    private Collection $clientCollection;
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
     * @var array configuration for the external clients base authentication URL.
     */
    private array $baseAuthUrl;
    /**
     * @var ClientInterface[] auth providers list.
     */
    private array $clients;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(Collection $clientCollection, UrlGeneratorInterface $urlGenerator)
    {
        $this->clientCollection = $clientCollection;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Initializes the widget.
     */
    public function init(): void
    {
        $view = Yii::getApp()->getView();
        if ($this->popupMode) {
            AuthChoiceAsset::register($view);
            if (empty($this->clientOptions)) {
                $options = '';
            } else {
                $options = Json::htmlEncode($this->clientOptions);
            }
            $view->registerJs("jQuery('#" . $this->getId() . "').authchoice({$options});");
        } else {
            AuthChoiceStyleAsset::register($view);
        }
        $this->options['id'] = $this->getId();
        echo Html::beginTag('div', $this->options);
    }

    /**
     * Runs the widget.
     *
     * @return string rendered HTML.
     */
    public function run(): string
    {
        $content = '';
        if ($this->autoRender) {
            $content .= $this->renderMainContent();
        }
        $content .= Html::endTag('div');
        return $content;
    }

    /**
     * Renders the main content, which includes all external services links.
     *
     * @throws InvalidConfigException
     * @throws \Yiisoft\Factory\Exceptions\InvalidConfigException
     *
     * @return string generated HTML.
     */
    protected function renderMainContent(): string
    {
        $items = [];
        foreach ($this->getClients() as $externalService) {
            $items[] = Html::tag('li', $this->clientLink($externalService));
        }
        return Html::tag('ul', implode('', $items), ['class' => 'auth-clients']);
    }

    /**
     * @return ClientInterface[] auth providers
     */
    public function getClients(): array
    {
        if ($this->clients === null) {
            $this->clients = $this->defaultClients();
        }

        return $this->clients;
    }

    /**
     * @param ClientInterface[] $clients auth providers
     */
    public function setClients(array $clients): void
    {
        $this->clients = $clients;
    }

    /**
     * Returns default auth clients list.
     *
     * @return ClientInterface[] auth clients list.
     */
    protected function defaultClients(): array
    {
        return $this->clientCollection->getClients();
    }

    /**
     * Outputs client auth link.
     *
     * @param ClientInterface $client external auth client instance.
     * @param string $text link text, if not set - default value will be generated.
     * @param array $htmlOptions link HTML options.
     *
     * @throws InvalidConfigException on wrong configuration.
     * @throws \Yiisoft\Factory\Exceptions\InvalidConfigException
     *
     * @return string generated HTML.
     */
    public function clientLink($client, $text = null, array $htmlOptions = []): Widget
    {
        $viewOptions = $client->getViewOptions();

        if (empty($viewOptions['widget'])) {
            if ($text === null) {
                $text = Html::tag('span', '', ['class' => 'auth-icon ' . $client->getName()]);
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
                    $htmlOptions['data-popup-width'] = $viewOptions['popupWidth'];
                }
                if (isset($viewOptions['popupHeight'])) {
                    $htmlOptions['data-popup-height'] = $viewOptions['popupHeight'];
                }
            }
            return Html::a($text, $this->createClientUrl($client), $htmlOptions);
        }

        $widgetConfig = $viewOptions['widget'];
        if (!isset($widgetConfig['__class'])) {
            throw new InvalidConfigException('Widget config "class" parameter is missing');
        }
        /* @var $widgetClass Widget */
        $widgetClass = $widgetConfig['__class'];
        if (!(is_subclass_of($widgetClass, AuthChoiceItem::class))) {
            throw new InvalidConfigException('Item widget class must be subclass of "' . AuthChoiceItem::class . '"');
        }
        unset($widgetConfig['__class']);
        $widgetConfig['client'] = $client;
        $widgetConfig['authChoice'] = $this;
        return $widgetClass::widget($widgetConfig);
    }

    /**
     * Composes client auth URL.
     *
     * @param ClientInterface $client external auth client instance.
     *
     * @return string auth URL.
     */
    public function createClientUrl($client): string
    {
        $this->autoRender = false;
        $url = $this->getBaseAuthUrl();
        $url[$this->clientIdGetParamName] = $client->getName();

        return Url::to($url);
    }

    /**
     * @return array base auth URL configuration.
     */
    public function getBaseAuthUrl(): array
    {
        if (!is_array($this->baseAuthUrl)) {
            $this->baseAuthUrl = $this->defaultBaseAuthUrl();
        }

        return $this->baseAuthUrl;
    }

    /**
     * @param array $baseAuthUrl base auth URL configuration.
     */
    public function setBaseAuthUrl(array $baseAuthUrl): void
    {
        $this->baseAuthUrl = $baseAuthUrl;
    }

    /**
     * Composes default base auth URL configuration.
     *
     * @return array base auth URL configuration.
     */
    protected function defaultBaseAuthUrl(): array
    {
        $baseAuthUrl = [
            Yii::getApp()->controller->getRoute(),
        ];
        $params = Yii::getApp()->getRequest()->getQueryParams();
        unset($params[$this->clientIdGetParamName]);
        return array_merge($baseAuthUrl, $params);
    }
}
