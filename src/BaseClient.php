<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use yii\exceptions\InvalidConfigException;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

/**
 * BaseClient is a base Auth Client class.
 *
 * @see ClientInterface
 */
abstract class BaseClient implements ClientInterface
{
    /**
     * @var array authenticated user attributes.
     */
    private $userAttributes;
    /**
     * @var array map used to normalize user attributes fetched from external auth service
     * in format: normalizedAttributeName => sourceSpecification
     * 'sourceSpecification' can be:
     * - string, raw attribute name
     * - array, pass to raw attribute value
     * - callable, PHP callback, which should accept array of raw attributes and return normalized value.
     *
     * For example:
     *
     * ```php
     * 'normalizeUserAttributeMap' => [
     *      'about' => 'bio',
     *      'language' => ['languages', 0, 'name'],
     *      'fullName' => function ($attributes) {
     *          return $attributes['firstName'] . ' ' . $attributes['lastName'];
     *      },
     *  ],
     * ```
     */
    private $normalizeUserAttributeMap;
    /**
     * @var array view options in format: optionName => optionValue
     */
    private $viewOptions;

    private $httpClient;

    private $requestFactory;

    /**
     * @var StateStorageInterface state storage to be used.
     */
    private $stateStorage;

    public function __construct(\Psr\Http\Client\ClientInterface $httpClient, RequestFactoryInterface $requestFactory)
    {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->stateStorage = new DummyStateStorage();
    }

    /**
     * @param array $userAttributes list of user attributes
     */
    public function setUserAttributes($userAttributes)
    {
        $this->userAttributes = $this->normalizeUserAttributes($userAttributes);
    }

    /**
     * @return array list of user attributes
     */
    public function getUserAttributes(): array
    {
        if ($this->userAttributes === null) {
            $this->userAttributes = $this->normalizeUserAttributes($this->initUserAttributes());
        }

        return $this->userAttributes;
    }

    /**
     * @param array $normalizeUserAttributeMap normalize user attribute map.
     */
    public function setNormalizeUserAttributeMap($normalizeUserAttributeMap)
    {
        $this->normalizeUserAttributeMap = $normalizeUserAttributeMap;
    }

    /**
     * @return array normalize user attribute map.
     */
    public function getNormalizeUserAttributeMap()
    {
        if ($this->normalizeUserAttributeMap === null) {
            $this->normalizeUserAttributeMap = $this->defaultNormalizeUserAttributeMap();
        }

        return $this->normalizeUserAttributeMap;
    }

    /**
     * @param array $viewOptions view options in format: optionName => optionValue
     */
    public function setViewOptions($viewOptions)
    {
        $this->viewOptions = $viewOptions;
    }

    /**
     * @return array view options in format: optionName => optionValue
     */
    public function getViewOptions(): array
    {
        if ($this->viewOptions === null) {
            $this->viewOptions = $this->defaultViewOptions();
        }

        return $this->viewOptions;
    }

    /**
     * @param StateStorageInterface $stateStorage stage storage to be used.
     */
    public function setStateStorage(StateStorageInterface $stateStorage): void
    {
        $this->stateStorage = $stateStorage;
    }

    /**
     * Initializes authenticated user attributes.
     * @return array auth user attributes.
     */
    abstract protected function initUserAttributes();

    /**
     * Returns the default [[normalizeUserAttributeMap]] value.
     * Particular client may override this method in order to provide specific default map.
     * @return array normalize attribute map.
     */
    protected function defaultNormalizeUserAttributeMap()
    {
        return [];
    }

    /**
     * Returns the default [[viewOptions]] value.
     * Particular client may override this method in order to provide specific default view options.
     * @return array list of default [[viewOptions]]
     */
    protected function defaultViewOptions()
    {
        return [];
    }

    /**
     * Normalize given user attributes according to [[normalizeUserAttributeMap]].
     * @param array $attributes raw attributes.
     * @return array normalized attributes.
     * @throws InvalidConfigException on incorrect normalize attribute map.
     */
    protected function normalizeUserAttributes($attributes)
    {
        foreach ($this->getNormalizeUserAttributeMap() as $normalizedName => $actualName) {
            if (is_scalar($actualName)) {
                if (array_key_exists($actualName, $attributes)) {
                    $attributes[$normalizedName] = $attributes[$actualName];
                }
            } elseif (\is_callable($actualName)) {
                $attributes[$normalizedName] = $actualName($attributes);
            } elseif (\is_array($actualName)) {
                $haystack = $attributes;
                $searchKeys = $actualName;
                $isFound = true;
                while (($key = array_shift($searchKeys)) !== null) {
                    if (\is_array($haystack) && array_key_exists($key, $haystack)) {
                        $haystack = $haystack[$key];
                    } else {
                        $isFound = false;
                        break;
                    }
                }
                if ($isFound) {
                    $attributes[$normalizedName] = $haystack;
                }
            } else {
                throw new InvalidConfigException('Invalid actual name "' . gettype($actualName) . '" specified at "' . get_class($this) . '::normalizeUserAttributeMap"');
            }
        }

        return $attributes;
    }

    public function createRequest(string $method, string $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    /**
     * Sets persistent state.
     * @param string $key state key.
     * @param mixed $value state value
     * @return $this the object itself
     */
    protected function setState($key, $value)
    {
        $this->stateStorage->set($this->getStateKeyPrefix() . $key, $value);
        return $this;
    }

    /**
     * Returns persistent state value.
     * @param string $key state key.
     * @return mixed state value.
     */
    protected function getState($key)
    {
        return $this->stateStorage->get($this->getStateKeyPrefix() . $key);
    }

    /**
     * Removes persistent state value.
     * @param string $key state key.
     * @return bool success.
     */
    protected function removeState($key)
    {
        return $this->stateStorage->remove($this->getStateKeyPrefix() . $key);
    }

    /**
     * Returns session key prefix, which is used to store internal states.
     * @return string session key prefix.
     */
    protected function getStateKeyPrefix()
    {
        return \get_class($this) . '_' . $this->getName() . '_';
    }

    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->httpClient->sendRequest($request);
    }
}
