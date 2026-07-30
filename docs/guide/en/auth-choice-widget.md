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
