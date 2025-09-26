<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Asset;

use Yiisoft\Assets\AssetBundle;

/**
 * AuthChoiceAsset is an asset bundle for {@see AuthChoice} widget.
 *
 * @see AuthChoiceStyleAsset
 */
class AuthChoiceAsset extends AssetBundle
{
    /**
     * Note: Aliases convert to actual file paths
     * $basePath (below) is normally the publically accessible target path where $sourcePath 
     * files are copied to. 
     * 1. Check that your application's config/common/params.php yiisoft/aliases['aliases']
     *    contains '@assets' alias e.g. 
     * 
     *    '@root' => dirname(__DIR__, 2), 
     *    '@assets' => '@root/public/assets',
     *    '@assetsUrl' => '@baseUrl/assets',
     *    '@vendor' => '@root/vendor',  
     * 
     * 2. Check that '@assetsUrl' is also listed
     * 3. Check that the '@vendor' alias is also included as above.
     * 4. The alias should point to public/assets as seen above
     * 5. The public/assets folder's 8 digit subfolder e.g. ab615tyu will only 
     *    be included ... if the sourcePath files do not already exist in a pre-existing folder
     *    e.g. tr674trs 
     * 6. Register the Asset in your application's layout file e.g. 
     *    resources/views/layout/main.php
     *  
     * @var string|null
     */    
    public ?string $basePath = '@assets';
    
    public ?string $baseUrl = '@assetsUrl';
    
    public ?string $sourcePath = '@vendor/yiisoft/yii-auth-client/resources/assets';
        
    /**
     * @psalm-suppress NonInvariantDocblockPropertyType $js
     */
    public array $js = [
        'authchoice.js',
    ];

    public array $depends = [
        AuthChoiceStyleAsset::class,
    ];
}