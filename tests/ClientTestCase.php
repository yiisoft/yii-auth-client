<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Yiisoft\Yii\AuthClient\AuthClient;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

class ClientTestCase extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    /**
     * Creates test OAuth client instance.
     *
     * @return AuthClient oauth client.
     */
    protected function createClient()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)->getMock();

        return $this->getMockBuilder(AuthClient::class)
            ->setConstructorArgs([$httpClient, $this->getRequestFactory(), new SessionStateStorage(new Session())])
            ->onlyMethods(['initUserAttributes', 'getName', 'getTitle', 'buildAuthUrl'])
            ->getMock();
    }

    // Tests :

    public function testSetGet(): void
    {
        $client = $this->createClient();

        $userAttributes = [
            'attribute1' => 'value1',
            'attribute2' => 'value2',
        ];
        $client->setUserAttributes($userAttributes);
        $this->assertEquals($userAttributes, $client->getUserAttributes(), 'Unable to setup user attributes!');

        $normalizeUserAttributeMap = [
            'name' => 'some/name',
            'email' => 'some/email',
        ];
        $client->setNormalizeUserAttributeMap($normalizeUserAttributeMap);
        $this->assertEquals(
            $normalizeUserAttributeMap,
            $client->getNormalizeUserAttributeMap(),
            'Unable to setup normalize user attribute map!'
        );

        $viewOptions = [
            'option1' => 'value1',
            'option2' => 'value2',
        ];
        $client->setViewOptions($viewOptions);
        $this->assertEquals($viewOptions, $client->getViewOptions(), 'Unable to setup view options!');
    }

    /**
     * Data provider for {@see testNormalizeUserAttributes()}
     *
     * @return array test data
     */
    public function dataProviderNormalizeUserAttributes()
    {
        return [
            [
                [
                    'name' => 'raw/name',
                    'email' => 'raw/email',
                ],
                [
                    'raw/name' => 'name value',
                    'raw/email' => 'email value',
                ],
                [
                    'name' => 'name value',
                    'email' => 'email value',
                ],
            ],
            [
                [
                    'name' => fn ($attributes) => $attributes['firstName'] . ' ' . $attributes['lastName'],
                ],
                [
                    'firstName' => 'John',
                    'lastName' => 'Smith',
                ],
                [
                    'name' => 'John Smith',
                ],
            ],
            [
                [
                    'email' => ['emails', 'prime'],
                ],
                [
                    'emails' => [
                        'prime' => 'some@email.com',
                    ],
                ],
                [
                    'email' => 'some@email.com',
                ],
            ],
            [
                [
                    'email' => ['emails', 0],
                    'secondaryEmail' => ['emails', 1],
                ],
                [
                    'emails' => [
                        'some@email.com',
                    ],
                ],
                [
                    'email' => 'some@email.com',
                ],
            ],
            [
                [
                    'name' => 'file_get_contents',
                ],
                [
                    'file_get_contents' => 'value',
                ],
                [
                    'name' => 'value',
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataProviderNormalizeUserAttributes
     *
     * @depends      testSetGet
     *
     * @param array $normalizeUserAttributeMap
     * @param array $rawUserAttributes
     * @param array $expectedNormalizedUserAttributes
     */
    public function testNormalizeUserAttributes(
        $normalizeUserAttributeMap,
        $rawUserAttributes,
        $expectedNormalizedUserAttributes
    ): void {
        $client = $this->createClient();
        $client->setNormalizeUserAttributeMap($normalizeUserAttributeMap);

        $client->setUserAttributes($rawUserAttributes);
        $normalizedUserAttributes = $client->getUserAttributes();

        $this->assertEquals(
            array_merge($rawUserAttributes, $expectedNormalizedUserAttributes),
            $normalizedUserAttributes
        );
    }

    /**
     * @depends testSetGet
     */
    public function testCreateRequest(): void
    {
        $request = $this->createClient()->createRequest('GET', 'http://example.com/');
        $this->assertInstanceOf(RequestInterface::class, $request);
    }
}
