AuthChoice Widget
==================

This page assumes you've already registered the auth route as described in [Quick Start](quick-start.md).

## Adding the widget to the login view

There's a ready-to-use [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice]] widget for views. It relies on
[[\Yiisoft\Yii\AuthClient\Collection]] being available via DI to list the configured clients, and needs to know
the auth route name:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth') ?>
```

By default, it renders one link per configured client as Bootstrap buttons inside a button group,
with inline SVG icons. Links redirect to the auth route directly:

```html
<div class="btn-group" id="yii-auth-client">
    <a class="auth-link btn btn-primary" title="Google" href="/auth/google">
        <svg class="auth-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 268.152 273.883" preserveAspectRatio="xMidYMid meet">...</svg>
    </a>
    <a class="auth-link btn btn-primary" title="GitHub" href="/auth/github">
        <svg class="auth-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet">...</svg>
    </a>
</div>
```

To use a different CSS framework or customize styling, use the `options()`, `linkAttributes()`, and `iconAttributes()` methods:

```php
// For Tailwind CSS:
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'flex gap-2'])
    ->linkAttributes(['class' => 'px-4 py-2 bg-blue-500 text-white rounded'])
    ->iconAttributes(['class' => 'w-6 h-6']) ?>
```

### Popup mode

By default, `popupMode` is disabled (`false`). To enable popups, set `popupMode(true)`:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->popupMode(true) ?>
```

When enabled, popups are controlled via JavaScript (registered via [[\Yiisoft\Yii\AuthClient\Asset\AuthChoiceAsset]])
and can be customized using [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientOptions()]].

### Customizing the buttons

Use `begin()`/`end()` with [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::getClients()]] and
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientLink()]] instead of the auto-rendered markup above if you
need different wrapper markup, custom link text, or extra HTML attributes per button:

```php
<?php
$authChoice = Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()->authRoute('site/auth');
$authChoice->begin();
?>
<div class="auth-buttons">
    <?php foreach ($authChoice->getClients() as $client): ?>
        <?= $authChoice->clientLink($client, 'Sign in with ' . $client->getTitle(), ['class' => 'btn']) ?>
    <?php endforeach; ?>
</div>
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::end() ?>
```

which, per client, renders something like:

```html
<a class="auth-link btn btn-primary" title="Google" href="/auth/google">Sign in with Google</a>
```

`clientLink()`'s `$text` argument, when given, is HTML-encoded and replaces the default icon.
The `$htmlOptions` argument is merged into the link's HTML attributes: an explicit `class` replaces the
default, but the widget always appends `auth-link` and the default Bootstrap classes (`btn btn-primary`).
An explicit `title` replaces the default (the client's `getTitle()`). In popup mode, 
`data-popup-width`/`data-popup-height` are always taken from the client's `getViewOptions()` and can't be 
overridden this way.

### Passing options to the widget

Two methods let you pass options into the widget itself; both must be called before `begin()`/`render()` to
take effect.

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::options()]] sets the HTML attributes of the container `<div>`.
It **replaces** the default attributes entirely (default: `['class' => 'btn-group']` for Bootstrap), so 
include `class` yourself if you want styling - except `id`, which the widget always forces to `yii-auth-client` regardless of what you pass:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'btn-group my-auth-clients', 'data-testid' => 'auth-buttons']) ?>
```

```html
<div class="btn-group my-auth-clients" data-testid="auth-buttons" id="yii-auth-client">...</div>
```

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientOptions()]] passes options straight through to the
`authchoice(el, options)` JS call (see [authchoice.js](https://github.com/yiisoft/yii-auth-client/blob/master/resources/assets/authchoice.js))
that powers the popup window, and only has an effect in popup mode. It's merged (one level deep) with the
script's own defaults, so you only need to specify the keys you want to change:

```php
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->clientOptions([
        'triggerSelector' => '.my-auth-link', // which links the popup handler binds to; default 'a.auth-link'
        'popup' => ['width' => 600, 'height' => 500], // window.open() features; other popup defaults are kept
    ]) ?>
```

### Overriding default styling

By default, the widget uses Bootstrap classes (`btn btn-primary` for links, `btn-group` for container).
To use a different CSS framework or customize the styling, override the defaults using [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::linkAttributes()]]
and [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::iconAttributes()]]. These methods must be called before 
`begin()`/`render()`:

```php
// Override for a different framework (e.g., Tailwind)
<?= Yiisoft\Yii\AuthClient\Widget\AuthChoice::widget()
    ->authRoute('site/auth')
    ->options(['class' => 'flex gap-2'])                           // override container
    ->linkAttributes(['class' => 'px-4 py-2 bg-blue-600'])         // override link classes
    ->iconAttributes(['class' => 'w-6 h-6 mr-2'])                  // override icon styles
```

Note: `linkAttributes()` merges with the widget's own `auth-link` class, and applies to all
links rendered by `clientLink()` without an explicit `$text`. Users can still override on a per-link basis
by passing `$htmlOptions` to `clientLink()` directly.

### Icon, text, or both

[[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::displayMode()]] controls what each button shows by default, via
[[\Yiisoft\Yii\AuthClient\Widget\AuthChoiceDisplayMode]] (must be called before `begin()`/`render()`):

- `AuthChoiceDisplayMode::Icon` (default) - just the inline SVG icon.
- `AuthChoiceDisplayMode::Text` - just the client's `getTitle()`, HTML-encoded.
- `AuthChoiceDisplayMode::Both` - the icon, followed by the title wrapped in `<span class="auth-title ms-2">`.

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

This only affects the *default* content used when [[\Yiisoft\Yii\AuthClient\Widget\AuthChoice::clientLink()]]
is called without an explicit `$text` - passing your own `$text` (as in [Customizing the buttons](#customizing-the-buttons)
above) always overrides it regardless of `displayMode()`.

Icons are now rendered as inline SVG, eliminating the need for sprite stylesheets. With `AuthChoiceDisplayMode::Text` (no icons rendered), no icon assets are needed.

### Adding custom provider logos

By default, the widget has built-in logos for: Google, GitHub, Facebook, LinkedIn, Microsoft, X, TikTok, VKontakte, and Yandex. For custom OAuth providers (like Okta, Auth0, etc.), the widget falls back to the client's `getTitle()` as text.

To use a custom SVG logo for a provider not in the built-in registry, set it on the OAuth client instance using `setLogo()`. For example, when configuring an Okta client:

```php
// config/params.php or config/di.php
return [
    'yiisoft/yii-auth-client' => [
        'clients' => [
            'okta' => [
                'class' => Yiisoft\Yii\AuthClient\Client\OpenIdConnect::class,
                'clientId' => 'your_client_id',
                'clientSecret' => 'your_client_secret',
                'issuerUrl' => 'https://your-domain.okta.com',
                'logo' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" viewBox="0 0 512 512"><path d="M281.086 9.305 270.8 136.057a129 129 0 0 0-14.732-.834c-6.254 0-12.37.417-18.346 1.39l-5.837-61.43c-.14-1.946 1.39-3.614 3.336-3.614h10.423l-5.003-62.125c-.14-1.946 1.39-3.614 3.196-3.614h34.051c1.946 0 3.475 1.668 3.197 3.614v-.14zm-85.892 6.254c-.556-1.807-2.501-2.919-4.308-2.224L158.92 25.01c-1.807.695-2.64 2.78-1.807 4.447l25.99 56.705-9.868 3.614c-1.807.695-2.64 2.78-1.807 4.447l26.546 55.732a119.2 119.2 0 0 1 30.993-11.813L195.333 15.559zM116.808 50.86l73.522 103.68c-9.312 6.116-17.79 13.343-25.017 21.682l-44.058-43.362c-1.39-1.39-1.25-3.614.14-4.865l8.06-6.671-43.78-44.336c-1.389-1.39-1.25-3.613.279-4.864l25.99-21.82c1.528-1.251 3.613-.973 4.725.556zM55.1 110.9c-1.53-1.112-3.753-.556-4.726 1.112l-16.956 29.464c-.973 1.668-.278 3.753 1.39 4.587l56.427 26.685-5.281 9.034c-.973 1.667-.278 3.891 1.529 4.586l56.149 25.712c4.03-10.424 9.45-20.153 16.122-28.909L55.1 110.902zm-41.556 80.054c.278-1.945 2.223-3.057 4.03-2.64l123 32.105c-3.197 10.424-5.003 21.403-5.281 32.8l-61.57-5.004a3.21 3.21 0 0 1-2.918-3.891l1.806-10.285-62.125-5.837c-1.946-.14-3.197-1.946-2.919-3.892l5.838-33.495.139.14zm-4.587 83.112c-1.946.14-3.196 1.946-2.918 3.892l5.976 33.495c.278 1.945 2.224 3.057 4.03 2.64l60.319-15.705 1.807 10.285c.278 1.946 2.223 3.058 4.03 2.64l59.485-16.4c-3.475-10.284-5.698-21.264-6.254-32.521L8.818 274.067h.14zm19.736 88.115c-.973-1.667-.278-3.752 1.39-4.586l114.8-54.481c4.308 10.284 10.145 19.874 17.094 28.491l-50.311 35.858c-1.53 1.112-3.753.695-4.726-.973l-5.281-9.173-51.285 35.44c-1.529 1.113-3.752.557-4.725-1.111l-17.095-29.465zm139.122-23.905-89.366 90.478c-1.39 1.39-1.251 3.614.278 4.865l26.128 21.82c1.53 1.25 3.614.973 4.726-.556l36.135-50.868 8.061 6.81c1.53 1.251 3.753.973 4.865-.694l35.024-50.868c-9.451-5.837-18.207-12.926-25.712-20.987zm-17.651 145.238c-1.807-.695-2.64-2.78-1.807-4.448l52.953-115.634c9.728 5.004 20.291 8.756 31.27 10.841l-15.565 59.763c-.417 1.806-2.502 2.918-4.309 2.223l-9.868-3.613-16.538 60.18c-.556 1.806-2.502 2.918-4.309 2.223l-31.966-11.674zm91.173-107.712-10.285 126.752c-.139 1.946 1.39 3.614 3.197 3.614h34.05c1.946 0 3.475-1.668 3.197-3.614l-5.003-62.125h10.423c1.946 0 3.475-1.668 3.336-3.614l-5.837-61.43c-5.977.973-12.092 1.39-18.346 1.39-5.003 0-9.868-.278-14.732-.973M363.92 32.653c.834-1.806 0-3.752-1.807-4.447l-31.966-11.674c-1.807-.695-3.753.417-4.309 2.224L309.3 78.936l-9.867-3.614c-1.807-.695-3.753.417-4.309 2.223l-15.566 59.763c11.119 2.224 21.542 5.976 31.271 10.84L363.92 32.655zm69.77 50.452-89.367 90.478a121.3 121.3 0 0 0-25.712-20.987l35.024-50.868c1.112-1.528 3.336-1.945 4.864-.695l8.061 6.81 36.136-50.867c1.112-1.529 3.336-1.807 4.725-.556l26.13 21.82c1.528 1.251 1.528 3.475.277 4.865h-.139zm48.365 71.159c1.807-.834 2.363-2.919 1.39-4.587l-17.095-29.464c-.973-1.668-3.196-2.085-4.725-1.112l-51.285 35.44-5.281-9.033c-.973-1.668-3.197-2.224-4.726-.973l-50.312 35.858c6.95 8.617 12.648 18.207 17.095 28.491l114.8-54.481zm18.068 46.142 5.837 33.495c.278 1.946-.972 3.614-2.918 3.892l-126.614 11.813c-.556-11.396-2.78-22.237-6.254-32.522l59.485-16.4c1.807-.556 3.752.695 4.03 2.64l1.807 10.286 60.319-15.705c1.806-.417 3.752.695 4.03 2.64zm-5.698 123c1.807.417 3.752-.695 4.03-2.64l5.838-33.495c.278-1.946-.973-3.614-2.919-3.892l-62.125-5.837 1.806-10.285c.278-1.946-.973-3.613-2.918-3.891l-61.57-5.004c-.278 11.397-2.085 22.376-5.281 32.8l123 32.105zm-32.8 76.44c-.973 1.669-3.197 2.086-4.726 1.113l-104.654-72.271c6.671-8.756 12.092-18.485 16.122-28.909l56.15 25.712c1.806.834 2.501 2.919 1.528 4.586l-5.281 9.034 56.427 26.685c1.668.834 2.363 2.919 1.39 4.586zM321.669 357.18l73.521 103.68c1.112 1.53 3.336 1.807 4.725.556l25.99-21.82c1.529-1.25 1.529-3.475.278-4.864l-43.78-44.336 8.062-6.671c1.528-1.251 1.528-3.475.139-4.865l-44.058-43.362c-7.366 8.339-15.705 15.705-25.017 21.681h.139zm-.695 141.207c-1.807.695-3.753-.417-4.308-2.224L283.032 373.58a119.2 119.2 0 0 0 30.993-11.813l26.546 55.732c.834 1.807 0 3.891-1.807 4.447l-9.868 3.614 25.99 56.705c.834 1.807 0 3.752-1.807 4.447l-31.966 11.675z"/></svg>
SVG,
            ],
        ],
    ],
];
```

The `logo` config key is applied to the client instance via DI, calling `setLogo()`. The widget will render this custom logo instead of falling back to the registry or the provider's title.
