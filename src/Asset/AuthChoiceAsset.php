<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 */
final class AuthChoiceAsset extends AssetBundle
{
    public ?string $basePath = '@assets';

    public ?string $baseUrl = '@assetsUrl';

    /** @var array */
    public array $jsStrings = [
        'authchoice-init' => <<<'JS_WRAP'
        for (const container of document.querySelectorAll('[data-authchoice]')) {
            const json = container.dataset.authchoice;
            authchoice(container, json ? JSON.parse(json) : {});
        }
        JS_WRAP,
    ];

    public ?string $sourcePath = '@vendor/yiisoft/yii-auth-client/resources/assets';

    /** @var array */
    public array $js = [
        'authchoice.js',
    ];
}
