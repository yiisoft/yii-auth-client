<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Clients;

use Yiisoft\Yii\AuthClient\Widgets\GooglePlusButton;

/**
 * GoogleHybrid is an enhanced version of the [[Google]], which uses Google+ hybrid sign-in flow,
 * which relies on embedded JavaScript code to generate a sign-in button and handle user authentication dialog.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         '__class' => Yiisoft\Yii\AuthClient\Collection::class,
 *         'clients' => [
 *             'google' => [
 *                 '__class' => Yiisoft\Yii\AuthClient\Clients\GoogleHybrid::class,
 *                 'clientId' => 'google_client_id',
 *                 'clientSecret' => 'google_client_secret',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 * ```
 *
 * Note: Google+ hybrid relies heavily on client-side JavaScript during authorization process, do not attempt to
 * obtain authorization code using [[buildAuthUrl()]] unless you absolutely sure, what you are doing.
 *
 * JavaScript button itself generated by [[Yiisoft\Yii\AuthClient\Widgets\GooglePlusButton]] widget. If you are using
 * [[Yiisoft\Yii\AuthClient\Widgets\AuthChoice]] it will appear automatically. Otherwise you need to add it into your page manually.
 * You may customize its appearance using 'widget' key at [[viewOptions]]:
 *
 * ```php
 * 'google' => [
 *     // ...
 *     'viewOptions' => [
 *         'widget' => [
 *             '__class' => Yiisoft\Yii\AuthClient\Widgets\GooglePlusButton::class,
 *             'buttonHtmlOptions' => [
 *                 'data-approvalprompt' => 'force'
 *             ],
 *         ],
 *     ],
 * ],
 * ```
 *
 * @see Google
 * @see \Yiisoft\Yii\AuthClient\Widgets\GooglePlusButton
 * @link https://developers.google.com/+/web/signin
 */
final class GoogleHybrid extends Google
{
    protected bool $validateAuthState = false;


    protected function defaultReturnUrl()
    {
        return 'postmessage';
    }

    protected function defaultViewOptions(): array
    {
        return [
            'widget' => [
                '__class' => GooglePlusButton::class
            ],
        ];
    }
}
