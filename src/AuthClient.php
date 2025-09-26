<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;

/**
 * AuthClient is a base Auth Client class.
 *
 * @see AuthClientInterface
 */
abstract class AuthClient implements AuthClientInterface
{
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
    protected array $normalizeUserAttributeMap = [];

    /**
     * @var array $viewOptions view options in format: optionName => optionValue
     */
    protected array $viewOptions;

    public function __construct(
        protected PsrClientInterface $httpClient,
        protected RequestFactoryInterface $requestFactory,
        /**
         * @var StateStorageInterface state storage to be used.
         */
        private readonly StateStorageInterface $stateStorage
    ) {
    }

    public function setRequestFactory(RequestFactoryInterface $requestFactory): void
    {
        $this->requestFactory = $requestFactory;
    }

    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    /**
     * @return array normalize user attribute map.
     */
    public function getNormalizeUserAttributeMap(): array
    {
        if (empty($this->normalizeUserAttributeMap)) {
            $this->normalizeUserAttributeMap = $this->defaultNormalizeUserAttributeMap();
        }

        return $this->normalizeUserAttributeMap;
    }

    /**
     * Returns the default {@see normalizeUserAttributeMap} value.
     * Particular client may override this method in order to provide specific default map.
     *
     * @return array normalize attribute map.
     *
     * @psalm-return array<never, never>
     */
    protected function defaultNormalizeUserAttributeMap(): array
    {
        return [];
    }

    /**
     * @return array view options in format: optionName => optionValue
     */
    #[\Override]
    public function getViewOptions(): array
    {
        if (empty($this->viewOptions)) {
            $this->viewOptions = $this->defaultViewOptions();
        }

        return $this->viewOptions;
    }

    /**
     * Returns the default {@see viewOptions} value.
     * Particular client may override this method in order to provide specific default view options.
     *
     * @return array list of default {@see viewOptions}
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 480}
     */
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 480,
        ];
    }

    #[\Override]
    abstract public function buildAuthUrl(ServerRequestInterface $incomingRequest, array $params): string;

    public function createRequest(string $method, string $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    /**
     * Sets persistent state.
     *
     * @param string $key state key.
     * @param mixed $value state value
     *
     * @return $this the object itself
     */
    protected function setState(string $key, $value): self
    {
        $this->stateStorage->set($this->getStateKeyPrefix() . $key, $value);
        return $this;
    }

    /**
     * Returns session key prefix, which is used to store internal states.
     *
     * @return string session key prefix.
     */
    protected function getStateKeyPrefix(): string
    {
        return static::class . '_' . $this->getName() . '_';
    }

    /**
     * Returns persistent state value.
     *
     * @param string $key state key.
     *
     * @return mixed state value.
     */
    protected function getState(string $key): mixed
    {
        return $this->stateStorage->get($this->getStateKeyPrefix() . $key);
    }

    /**
     * Removes persistent state value.
     *
     * @param string $key state key.
     */
    protected function removeState(string $key): void
    {
        $this->stateStorage->remove($this->getStateKeyPrefix() . $key);
    }

    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->httpClient->sendRequest($request);
    }
}
