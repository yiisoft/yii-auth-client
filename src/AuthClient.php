<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Override;

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
    protected array $viewOptions = [];

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
     * Returns the authenticated user's attributes, as fetched by {@see initUserAttributes()} and normalized
     * according to {@see normalizeUserAttributeMap}.
     *
     * @return array user attributes.
     */
    public function getUserAttributes(): array
    {
        $attributes = $this->initUserAttributes();
        $normalizeMap = $this->getNormalizeUserAttributeMap();

        return array_merge($attributes, $this->normalizeUserAttributes($attributes, $normalizeMap));
    }

    /**
     * Fetches the authenticated user's raw attribute data from the external auth provider.
     * Particular client should override this method in order to provide actual attribute fetching.
     *
     * @return array raw user attributes.
     */
    protected function initUserAttributes(): array
    {
        return [];
    }

    /**
     * Applies {@see normalizeUserAttributeMap} to raw user attributes.
     *
     * @param array $attributes raw user attributes.
     * @param array $normalizeMap normalize attribute map.
     *
     * @return array normalized attributes, keyed by their normalized name.
     */
    private function normalizeUserAttributes(array $attributes, array $normalizeMap): array
    {
        $normalized = [];

        foreach ($normalizeMap as $normalizedName => $sourceSpecification) {
            if (is_callable($sourceSpecification)) {
                $normalized[$normalizedName] = $sourceSpecification($attributes);
                continue;
            }

            if (is_array($sourceSpecification)) {
                $value = $attributes;
                foreach ($sourceSpecification as $key) {
                    /** @var array-key $key path segment, per the {@see normalizeUserAttributeMap} format. */
                    if (!is_array($value) || !array_key_exists($key, $value)) {
                        $value = null;
                        /**
                         * @infection-ignore-all
                         * Once $value is null, every remaining iteration re-hits the `!is_array($value)`
                         * branch of the guard above and re-assigns null, so `break` vs `continue` here is
                         * unobservable: both leave $value null after the loop.
                         */
                        break;
                    }
                    $value = $value[$key];
                }
                $normalized[$normalizedName] = $value;
                continue;
            }

            /**
             * @infection-ignore-all
             * Per the {@see normalizeUserAttributeMap} format, $sourceSpecification is a raw attribute name
             * here. PHP normalizes any array-key-compatible scalar identically whether cast to string first
             * or not, so this cast is unobservable for every value this format allows; it exists only to
             * narrow the type for static analysis.
             */
            $normalized[$normalizedName] = $attributes[(string) $sourceSpecification] ?? null;
        }

        return $normalized;
    }

    /**
     * @return array view options in format: optionName => optionValue
     */
    #[Override]
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

    #[Override]
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
