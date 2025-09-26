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
    
    public function getButtonClass(): string;
        
    /**
     * The Client id is publically visible in button urls
     * The Client secret must not be made available publically => exclude from interface
     * 
     * @return string
     */
    public function getClientId(): string;
    
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params): string;
}
