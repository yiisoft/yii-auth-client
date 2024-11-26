<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\ServerRequestInterface;

/**
 * ClientInterface declares basic interface all Auth clients should follow.
 */
interface AuthClientInterface
{
    /**
     * @return string service name.
     */
    public function getName(): string;

    /**
     * @return string service title.
     */
    public function getTitle(): string;

    /**
     * @return array view options in format: optionName => optionValue
     */
    public function getViewOptions(): array;

    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params): string;
}
