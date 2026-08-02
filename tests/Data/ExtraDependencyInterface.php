<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Data;

/**
 * Stands in for an arbitrary third-party dependency a custom auth client might require, used to prove
 * client instantiation resolves dependencies beyond the fixed set built into every built-in client.
 */
interface ExtraDependencyInterface {}
