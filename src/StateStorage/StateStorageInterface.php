<?php

namespace Yiisoft\Yii\AuthClient\StateStorage;

/**
 * StateStorageInterface is an interface for Auth client state storage.
 *
 * Herein 'state' means a named variable, which is persistent between different requests.
 *
 * Note: in order to function correctly state storage should vary depending on application session,
 * e.g. different web users should not share state with the same name.
 */
interface StateStorageInterface
{
    /**
     * Adds a state variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key variable name
     * @param mixed $value variable value
     */
    public function set($key, $value);

    /**
     * Returns the state variable value with the variable name.
     * If the variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the variable name
     * @return mixed the variable value, or `null` if the variable does not exist.
     */
    public function get($key);

    /**
     * Removes a state variable.
     * @param string $key the name of the variable to be removed
     * @return bool success.
     */
    public function remove($key);
}
