<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient;

/**
 * ClientInterface declares basic interface all Auth clients should follow.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
interface ClientInterface
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
     * @return array list of user attributes
     */
    public function getUserAttributes(): array;

    /**
     * @return array view options in format: optionName => optionValue
     */
    public function getViewOptions(): array;
}
