<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\AuthClient\RequestUtil;

final class RequestUtilTest extends TestCase
{
    public function testComposeUrlWithoutParams(): void
    {
        $url = RequestUtil::composeUrl('http://example.com/path');

        $this->assertSame('http://example.com/path', $url);
    }

    public function testComposeUrlAddsQuestionMarkWhenNoneExists(): void
    {
        $url = RequestUtil::composeUrl('http://example.com/path', ['a' => '1']);

        $this->assertSame('http://example.com/path?a=1', $url);
    }

    public function testComposeUrlAppendsAmpersandWhenQueryAlreadyExists(): void
    {
        $url = RequestUtil::composeUrl('http://example.com/path?existing=1', ['a' => '1']);

        $this->assertSame('http://example.com/path?existing=1&a=1', $url);
    }

    public function testAddParamsMergesIntoExistingQuery(): void
    {
        $request = new Request('GET', 'http://example.com/path?existing=1');

        $newRequest = RequestUtil::addParams($request, ['a' => '2']);

        $this->assertSame('existing=1&a=2', $newRequest->getUri()->getQuery());
    }

    public function testAddParamsDoesNotMutateOriginalRequest(): void
    {
        $request = new Request('GET', 'http://example.com/path?existing=1');

        RequestUtil::addParams($request, ['a' => '2']);

        $this->assertSame('existing=1', $request->getUri()->getQuery());
    }

    public function testAddParamsOverwritesExistingKey(): void
    {
        $request = new Request('GET', 'http://example.com/path?a=1');

        $newRequest = RequestUtil::addParams($request, ['a' => '2']);

        $this->assertSame('a=2', $newRequest->getUri()->getQuery());
    }

    public function testGetParamsReturnsEmptyArrayForNoQuery(): void
    {
        $request = new Request('GET', 'http://example.com/path');

        $params = RequestUtil::getParams($request);

        $this->assertSame([], $params);
    }

    public function testGetParamsParsesSimpleQuery(): void
    {
        $request = new Request('GET', 'http://example.com/path?a=1&b=2');

        $params = RequestUtil::getParams($request);

        $this->assertSame(['a' => '1', 'b' => '2'], $params);
    }

    public function testGetParamsCollectsRepeatedKeysIntoArray(): void
    {
        $request = new Request('GET', 'http://example.com/path?a=1&a=2');

        $params = RequestUtil::getParams($request);

        $this->assertSame(['a' => ['1', '2']], $params);
    }

    public function testGetParamsDecodesUrlEncodedValues(): void
    {
        $request = new Request('GET', 'http://example.com/path?a=hello%20world');

        $params = RequestUtil::getParams($request);

        $this->assertSame(['a' => 'hello world'], $params);
    }

    public function testAddHeadersSetsSingleHeader(): void
    {
        $request = new Request('GET', 'http://example.com/path');

        $newRequest = RequestUtil::addHeaders($request, ['X-Test' => 'value']);

        $this->assertSame('value', $newRequest->getHeaderLine('X-Test'));
    }

    public function testAddHeadersSetsMultipleHeaders(): void
    {
        $request = new Request('GET', 'http://example.com/path');

        $newRequest = RequestUtil::addHeaders($request, [
            'X-One' => 'one',
            'X-Two' => 'two',
        ]);

        $this->assertSame('one', $newRequest->getHeaderLine('X-One'));
        $this->assertSame('two', $newRequest->getHeaderLine('X-Two'));
    }

    public function testGetParamsPreservesEmbeddedEqualsSignInValue(): void
    {
        $request = new Request('GET', 'http://example.com/path?key=a%3Db');

        $params = RequestUtil::getParams($request);

        $this->assertSame(['key' => 'a=b'], $params);
    }

    public function testGetParamsReturnsNullForBareFlagWithoutEqualsSign(): void
    {
        $request = new Request('GET', 'http://example.com/path?flag');

        $params = RequestUtil::getParams($request);

        $this->assertSame(['flag' => null], $params);
    }

    public function testAddHeadersDoesNotMutateOriginalRequest(): void
    {
        $request = new Request('GET', 'http://example.com/path');

        RequestUtil::addHeaders($request, ['X-Test' => 'value']);

        $this->assertSame('', $request->getHeaderLine('X-Test'));
    }
}
