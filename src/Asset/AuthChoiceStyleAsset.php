<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 */
class AuthChoiceStyleAsset extends AssetBundle
{
    public ?string $sourcePath = __DIR__ . '../../resources/assets';
    
    /**
     * @psalm-suppress NonInvariantDocblockPropertyType $css
     */
    public array $css = [
        'authchoice.css',
    ];
}
