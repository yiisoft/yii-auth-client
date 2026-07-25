<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
use Yiisoft\Yii\AuthClient\Widget\AuthChoiceItem;

/**
 * Mock for a custom {@see AuthChoice} item widget, configurable via `viewOptions['widget']`.
 */
final class TestAuthChoiceItem extends AuthChoiceItem
{
    public function __construct(
        public readonly OAuth2 $client,
        public readonly AuthChoice $authChoice,
    ) {}

    public function render(): string
    {
        return 'auth-choice-item:' . $this->client->getName();
    }
}
