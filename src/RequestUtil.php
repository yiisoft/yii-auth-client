<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\RequestInterface;

use function is_array;

final class RequestUtil
{
    /**
     * Composes URL from base URL and GET params.
     *
     * @param string $url base URL.
     * @param array $params GET params.
     *
     * @return string composed URL.
     */
    public static function composeUrl(string $url, array $params = []): string
    {
        if (!empty($params)) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    public static function addParams(RequestInterface $request, array $params): RequestInterface
    {
        $currentParams = self::getParams($request);
        $newParams = array_merge($currentParams, $params);

        $uri = $request
            ->getUri()
            ->withQuery(http_build_query($newParams, '', '&', PHP_QUERY_RFC3986));
        return $request->withUri($uri);
    }

    public static function getParams(RequestInterface $request): array
    {
        $queryString = $request
            ->getUri()
            ->getQuery();
        if ($queryString === '') {
            return [];
        }

        $result = [];

        foreach (explode('&', $queryString) as $pair) {
            $parts = explode('=', $pair, 2);
            $key = rawurldecode($parts[0]);
            $value = isset($parts[1]) ? rawurldecode($parts[1]) : null;
            if (!isset($result[$key])) {
                $result[$key] = $value;
            } else {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            }
        }
        return $result;
    }

    public static function addHeaders(RequestInterface $request, array $headers): RequestInterface
    {
        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        }
        return $request;
    }
}
