Виджет AuthChoice
==================

Здесь предполагается, что маршрут аутентификации уже зарегистрирован, как описано в разделе
[Быстрый старт](quick-start.md).

## Требуемые псевдонимы для режима попапа

При использовании виджета в режиме попапа (по умолчанию) он автоматически регистрирует 
[[\Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset]]. Для работы пакета ресурсов ваше приложение должно 
определить псевдонимы `@assets` и `@assetsUrl` в `config/common/params.php`:

```php
'yiisoft/aliases' => [
    '@assets' => '@root/public/assets',
    '@assetsUrl' => '@baseUrl/assets',
],
```

- `@assets` — директория, где размещаются опубликованные ресурсы (должна быть доступна из веба)
- `@assetsUrl` — публичный URL-путь к `@assets`

Если вы отключите режим попапа через `popupMode(false)`, эти псевдонимы не требуются.

## Добавление виджета в представление входа

Для представлений есть готовый к использованию виджет [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]]. Он получает
список настроенных клиентов через DI-сервис [[\Yiisoft\Yii\AuthClient\Collection]] и должен знать имя маршрута
аутентификации:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth') ?>
```

По умолчанию он выводит кнопки Bootstrap для каждого настроенного клиента внутри группы кнопок,
со встроенными SVG иконками. Ссылки открывают процесс аутентификации во всплывающем окне (см. раздел
[Режим всплывающего окна](#режим-всплывающего-окна) ниже):

```html
<div class="btn-group" id="yii-auth-client" data-authchoice="{...}">
    <a class="auth-link btn btn-primary" title="Google" href="/auth/google" data-popup-width="860" data-popup-height="480">
        <svg class="auth-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 268.152 273.883" preserveAspectRatio="xMidYMid meet">...</svg>
    </a>
    <a class="auth-link btn btn-primary" title="GitHub" href="/auth/github" data-popup-width="860" data-popup-height="480">
        <svg class="auth-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet">...</svg>
    </a>
</div>
```

Для использования другого CSS-фреймворка или кастомизации стилей используйте методы `options()`, `linkAttributes()` 
и `iconAttributes()`:

```php
// Для Tailwind CSS:
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'flex gap-2'])
    ->linkAttributes(['class' => 'px-4 py-2 bg-blue-500 text-white rounded'])
    ->iconAttributes(['class' => 'w-6 h-6']) ?>
```

### Режим всплывающего окна

По умолчанию `popupMode` включён (`true`). Всплывающие окна управляются через JavaScript (подключается через
[[\Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset]]) и могут быть настроены с помощью
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientOptions()]]. Чтобы вместо этого выводить обычные ссылки,
установите `popupMode(false)`:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->popupMode(false) ?>
```

### Настройка кнопок

Если нужна другая обёртка, свой текст ссылки или дополнительные HTML-атрибуты для каждой кнопки, используйте
`begin()`/`end()` вместе с [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::getClients()]] и
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientLink()]] вместо автоматически генерируемой разметки выше:

```php
<?php
$authChoice = Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth');
$authChoice->begin();
?>
<div class="auth-buttons">
    <?php foreach ($authChoice->getClients() as $client): ?>
        <?= $authChoice->clientLink($client, 'Войти через ' . $client->getTitle(), ['class' => 'btn']) ?>
    <?php endforeach; ?>
</div>
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::end() ?>
```

что для каждого клиента выведет что-то вроде:

```html
<a class="auth-link btn btn-primary" title="Google" href="/auth/google">Войти через Google</a>
```

Аргумент `$text` метода `clientLink()`, если он передан, экранируется и заменяет собой иконку по умолчанию. 
Аргумент `$htmlOptions` объединяется с HTML-атрибутами ссылки: виджет всегда добавляет `auth-link`, 
но если вы указали явно `class`, стандартные классы Bootstrap (`btn btn-primary`) не добавляются.
Явно указанный `title` заменяет значение по умолчанию (`getTitle()` клиента). В режиме 
попапа атрибуты `data-popup-width`/`data-popup-height` всегда берутся из `getViewOptions()` клиента и не могут 
быть переопределены через `$htmlOptions`.

### Передача опций в виджет

Два метода позволяют передать опции самому виджету; оба нужно вызывать до `begin()`/`render()`, чтобы они
подействовали.

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::options()]] задаёт HTML-атрибуты контейнера `<div>`. Он
**полностью заменяет** атрибуты по умолчанию (по умолчанию: `['class' => 'btn-group']` для Bootstrap),
поэтому укажите `class` самостоятельно, если нужна стилизация:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'btn-group my-auth-clients', 'data-testid' => 'auth-buttons']) ?>
```

```html
<div class="btn-group my-auth-clients" data-testid="auth-buttons">...</div>
```

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientOptions()]] передаёт опции напрямую в вызов JS-функции
`authchoice(el, options)` (см. [authchoice.js](https://github.com/yiisoft/yii-auth-client/blob/master/resources/assets/authchoice.js)),
которая управляет всплывающим окном, и действует только в режиме попапа. Опции объединяются (на один
уровень вложенности) со значениями по умолчанию из скрипта, поэтому достаточно указать только те ключи,
которые нужно изменить:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->clientOptions([
        'triggerSelector' => '.my-auth-link', // к каким ссылкам привязывается обработчик попапа; по умолчанию 'a.auth-link'
        'popup' => ['width' => 600, 'height' => 500], // параметры window.open(); остальные значения popup сохраняются
    ]) ?>
```

### Иконка, текст или и то, и другое

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::displayMode()]] управляет тем, что выводится в каждой кнопке по
умолчанию, через [[\Yiisoft\Yii\AuthClient\Widget\AuthChoiceDisplayMode]] (нужно вызывать до
`begin()`/`render()`):

- `AuthChoiceDisplayMode::Icon` (по умолчанию) - только встроенная SVG иконка.
- `AuthChoiceDisplayMode::Text` - только `getTitle()` клиента, экранированный.
- `AuthChoiceDisplayMode::Both` - иконка, за которой следует заголовок, обёрнутый в `<span class="auth-title ms-2">`.

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->displayMode(Yiisoft\Yii\AuthClient\Widget\AuthChoiceDisplayMode::Both) ?>
```

```html
<div class="btn-group" id="yii-auth-client">
    <a class="auth-link btn btn-primary" title="Google" href="/auth/google">
        <svg class="auth-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 268.152 273.883" preserveAspectRatio="xMidYMid meet">...</svg><span class="auth-title ms-2">Google</span>
    </a>
</div>
```

Это влияет только на содержимое *по умолчанию*, которое используется, когда
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientLink()]] вызывается без явного `$text` - если вы передаёте
свой `$text` (как в разделе [Настройка кнопок](#настройка-кнопок) выше), он всегда переопределяет вывод,
независимо от `displayMode()`.

Иконки теперь отображаются встроенным SVG, что исключает необходимость в спрайтах стилей. При `AuthChoiceDisplayMode::Text` (иконки не выводятся) никакие ресурсы иконок не требуются.

### Переопределение стилей по умолчанию

По умолчанию виджет использует классы Bootstrap (`btn btn-primary` для ссылок, `btn-group` для контейнера).
Чтобы использовать другой CSS-фреймворк или кастомизировать стили, переопределите значения по умолчанию с помощью
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::linkAttributes()]] и 
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::iconAttributes()]]. Эти методы должны быть вызваны до 
`begin()`/`render()`:

```php
// Переопределение для другого фреймворка (например, Tailwind)
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'flex gap-2'])                           // переопределить контейнер
    ->linkAttributes(['class' => 'px-4 py-2 bg-blue-600'])         // переопределить классы ссылок
    ->iconAttributes(['class' => 'w-6 h-6 mr-2'])                  // переопределить стили иконок
```

Примечание: `linkAttributes()` объединяется с собственным классом виджета `auth-link` и применяется ко всем
ссылкам, отрисованным методом `clientLink()` без явного `$text`. Пользователи могут переопределить 
на основе отдельной ссылки, передав `$htmlOptions` методу `clientLink()` напрямую.

### Добавление пользовательских логотипов провайдеров

По умолчанию виджет имеет встроенные логотипы для: Google, GitHub, Facebook, LinkedIn, Microsoft, X, TikTok, 
VKontakte, Yandex и Discord. Для пользовательских OAuth-провайдеров (таких как Okta, Auth0 и т. д.) виджет возвращается к 
`getTitle()` клиента как текст.

Чтобы использовать пользовательский логотип SVG, установите ключ `logo` при определении клиента. Он будет применён 
через DI, вызвав `setLogo()` на экземпляре клиента:

```php
// config/params.php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'okta' => [
                'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
                'clientId' => 'your_client_id',
                'clientSecret' => 'your_client_secret',
                'issuerUrl' => 'https://your-domain.okta.com',
                'logo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><!-- ваш SVG здесь --></svg>',
            ],
        ],
    ],
];
```

Виджет вставит это значение как есть, без изменений, вместо возврата к реестру или названию провайдера - он не
добавляет оборачивающий `<svg>`, не подставляет `width`/`height`/`viewBox` и не применяет
{@see AuthChoice::iconAttributes()}. Убедитесь, что ваш SVG - это законченный самодостаточный элемент со своими
`xmlns`, `viewBox`, размерами и классами (например, `class="auth-icon"`), нужными для стилизации.
