<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;

/**
 * OpenId provides a simple interface for OpenID (1.1 and 2.0) authentication.
 * Supports Yadis and HTML discovery.
 *
 * Usage:
 *
 * ```php
 * use Yiisoft\Yii\AuthClient\OpenId;
 * ...
 * $client = new OpenId();
 * $client->setAuthUrl = 'https://open.id.provider.url'; // Setup provider endpoint
 * $url = $client->buildAuthUrl(); // Get authentication URL
 * return $this->responseFactory->createResponse(302)->withHeader('Location', $url); // Redirect to authentication URL
 * // After user returns at our site:
 * if ($client->validate()) { // validate response
 *     $userAttributes = $client->getUserAttributes(); // get account info
 *     ...
 * }
 * ```
 *
 * AX and SREG extensions are supported.
 * To use them, specify {@see requiredAttributes} and/or {@see optionalAttributes}.
 *
 * @link http://openid.net/
 */
class OpenId extends BaseClient
{
    /**
     * @var string authentication base URL, which should be used to compose actual authentication URL
     * by {@see buildAuthUrl()} method.
     */
    private string $authUrl;
    /**
     * @var array list of attributes, which always should be returned from server.
     * Attribute names should be always specified in AX format.
     * For example:
     *
     * ```php
     * ['namePerson/friendly', 'contact/email']
     * ```
     */
    private array $requiredAttributes = [];
    /**
     * @var array list of attributes, which could be returned from server.
     * Attribute names should be always specified in AX format.
     * For example:
     *
     * ```php
     * ['namePerson/first', 'namePerson/last']
     * ```
     */
    private array $optionalAttributes = [];
    /**
     * @var bool whether to verify the peer's certificate.
     */
    private bool $verifyPeer;
    /**
     * @var string directory that holds multiple CA certificates.
     * This value will take effect only if [[verifyPeer]] is set.
     */
    private string $capath;
    /**
     * @var string the name of a file holding one or more certificates to verify the peer with.
     * This value will take effect only if [[verifyPeer]] is set.
     */
    private string $cainfo;
    /**
     * @var array data, which should be used to retrieve the OpenID response.
     * If not set combination of GET and POST will be used.
     */
    private array $data;
    /**
     * @var array map of matches between AX and SREG attribute names in format: axAttributeName => sregAttributeName
     */
    private array $axToSregMap = [
        'namePerson/friendly' => 'nickname',
        'contact/email' => 'email',
        'namePerson' => 'fullname',
        'birthDate' => 'dob',
        'person/gender' => 'gender',
        'contact/postalCode/home' => 'postcode',
        'contact/country/home' => 'country',
        'pref/language' => 'language',
        'pref/timezone' => 'timezone',
    ];

    /**
     * @var string authentication return URL.
     */
    private string $returnUrl;
    /**
     * @var string claimed identifier (identity)
     */
    private string $claimedId;
    /**
     * @var string client trust root (realm), by default [[\yii\web\Request::hostInfo]] value will be used.
     */
    private string $trustRoot;

    /**
     * @param string $claimedId claimed identifier (identity).
     */
    public function setClaimedId(string $claimedId): void
    {
        $this->claimedId = $claimedId;
    }

    /**
     * @return string claimed identifier (identity).
     */
    public function getClaimedId(): string
    {
        if ($this->claimedId === null) {
            if (isset($this->data['openid_claimed_id'])) {
                $this->claimedId = $this->data['openid_claimed_id'];
            } elseif (isset($this->data['openid_identity'])) {
                $this->claimedId = $this->data['openid_identity'];
            }
        }

        return $this->claimedId;
    }

    /**
     * @param string $returnUrl authentication return URL.
     */
    public function setReturnUrl(string $returnUrl): void
    {
        $this->returnUrl = $returnUrl;
    }

    /**
     * @return string authentication return URL.
     */
    public function getReturnUrl(ServerRequestInterface $incomingRequest): string
    {
        if ($this->returnUrl === null) {
            $this->returnUrl = $this->defaultReturnUrl($incomingRequest);
        }

        return $this->returnUrl;
    }

    /**
     * @param string $value client trust root (realm).
     */
    public function setTrustRoot(string $value): void
    {
        $this->trustRoot = $value;
    }

    /**
     * @return string client trust root (realm).
     */
    public function getTrustRoot(): string
    {
        return $this->trustRoot;
    }

    /**
     * Generates default {@see returnUrl} value.
     * @param ServerRequestInterface $incomingRequest
     * @return string default authentication return URL.
     */
    protected function defaultReturnUrl(ServerRequestInterface $incomingRequest): string
    {
        $params = $incomingRequest->getQueryParams();
        foreach ($params as $name => $value) {
            if (strncmp('openid', $name, 6) === 0) {
                unset($params[$name]);
            }
        }
        return $incomingRequest
            ->getUri()
            ->withQuery(http_build_query($params, '', '&', PHP_QUERY_RFC3986))
            ->__toString();
    }

    /**
     * Combines given URLs into single one.
     * @param string $baseUrl base URL.
     * @param string|array $additionalUrl additional URL string or information array.
     * @return string composed URL.
     */
    protected function buildUrl(string $baseUrl, $additionalUrl): string
    {
        $baseUrl = parse_url($baseUrl);
        if (!is_array($additionalUrl)) {
            $additionalUrl = parse_url($additionalUrl);
        }

        if (isset($baseUrl['query'], $additionalUrl['query'])) {
            $additionalUrl['query'] = $baseUrl['query'] . '&' . $additionalUrl['query'];
        }

        $urlInfo = array_merge($baseUrl, $additionalUrl);
        return $urlInfo['scheme'] . '://'
            . (empty($urlInfo['username']) ? ''
                : (empty($urlInfo['password']) ? "{$urlInfo['username']}@"
                    : "{$urlInfo['username']}:{$urlInfo['password']}@"))
            . $urlInfo['host']
            . (empty($urlInfo['port']) ? '' : ":{$urlInfo['port']}")
            . (empty($urlInfo['path']) ? '' : $urlInfo['path'])
            . (empty($urlInfo['query']) ? '' : "?{$urlInfo['query']}")
            . (empty($urlInfo['fragment']) ? '' : "#{$urlInfo['fragment']}");
    }

    /**
     * Scans content for <meta>/<link> tags and extract information from them.
     * @param string $content HTML content to be be parsed.
     * @param string $tag name of the source tag.
     * @param string $matchAttributeName name of the source tag attribute, which should contain $matchAttributeValue
     * @param string $matchAttributeValue required value of $matchAttributeName
     * @param string $valueAttributeName name of the source tag attribute, which should contain searched value.
     * @return string|bool searched value, "false" on failure.
     */
    protected function extractHtmlTagValue(
        string $content,
        string $tag,
        string $matchAttributeName,
        string $matchAttributeValue,
        string $valueAttributeName
    ) {
        preg_match_all(
            "#<{$tag}[^>]*$matchAttributeName=['\"].*?$matchAttributeValue.*?['\"][^>]*$valueAttributeName=['\"](.+?)['\"][^>]*/?>#i",
            $content,
            $matches1
        );
        preg_match_all(
            "#<{$tag}[^>]*$valueAttributeName=['\"](.+?)['\"][^>]*$matchAttributeName=['\"].*?$matchAttributeValue.*?['\"][^>]*/?>#i",
            $content,
            $matches2
        );
        $result = array_merge($matches1[1], $matches2[1]);

        return empty($result) ? false : $result[0];
    }

    /**
     * Performs Yadis and HTML discovery.
     * @param string $url Identity URL.
     * @return array OpenID provider info, following keys will be available:
     *
     * - url: string, OP Endpoint (i.e. OpenID provider address).
     * - version: int, OpenID protocol version used by provider.
     * - identity: string, identity value.
     * - identifier_select: bool, whether to request OP to select identity for an user in OpenID 2, does not affect OpenID 1.
     * - ax: bool, whether AX attributes should be used.
     * - sreg: bool, whether SREG attributes should be used.
     */
    public function discover(string $url): array
    {
        if (empty($url)) {
            throw new \RuntimeException('No identity supplied.');
        }
        $result = [
            'url' => null,
            'version' => null,
            'identity' => $url,
            'identifier_select' => false,
            'ax' => false,
            'sreg' => false,
        ];

        // Use xri.net proxy to resolve i-name identities
        if (!preg_match('#^https?:#', $url)) {
            $url = 'https://xri.net/' . $url;
        }

        /* We save the original url in case of Yadis discovery failure.
        It can happen when we'll be lead to an XRDS document
        which does not have any OpenID2 services.*/
        $originalUrl = $url;

        // A flag to disable yadis discovery in case of failure in headers.
        $yadis = true;

        // We'll jump a maximum of 5 times, to avoid endless redirections.
        for ($i = 0; $i < 5; $i++) {
            if ($yadis) {
                $request = $this->createRequest('HEAD', $url);
                $headers = [];
                $response = $this->sendRequest($request);
                foreach ($response->getHeaders() as $name => $values) {
                    $headers[strtolower($name)] = array_pop($values);
                }

                $next = false;
                if (isset($headers['x-xrds-location'])) {
                    $url = $this->buildUrl($url, trim($headers['x-xrds-location']));
                    $next = true;
                }

                if (isset($headers['content-type'])
                    && (strpos($headers['content-type'], 'application/xrds+xml') !== false
                        || strpos($headers['content-type'], 'text/xml') !== false)
                ) {
                    /* Apparently, some providers return XRDS documents as text/html.
                    While it is against the spec, allowing this here shouldn't break
                    compatibility with anything.
                    ---
                    Found an XRDS document, now let's find the server, and optionally delegate.*/
                    $content = $this->sendRequest(
                        $request->withMethod('GET')->withUri($request->getUri()->withPath($url))
                    )->getBody()->getContents();

                    preg_match_all('#<Service.*?>(.*?)</Service>#s', $content, $m);
                    foreach ($m[1] as $content) {
                        $content = ' ' . $content; // The space is added, so that strpos doesn't return 0.

                        // OpenID 2
                        $ns = preg_quote('http://specs.openid.net/auth/2.0/', '#');
                        if (preg_match('#<Type>\s*' . $ns . '(server|signon)\s*</Type>#s', $content, $type)) {
                            if ($type[1] === 'server') {
                                $result['identifier_select'] = true;
                            }

                            preg_match('#<URI.*?>(.*)</URI>#', $content, $server);
                            preg_match('#<(Local|Canonical)ID>(.*)</\1ID>#', $content, $delegate);
                            if (empty($server)) {
                                throw new \RuntimeException('No servers found!');
                            }
                            // Does the server advertise support for either AX or SREG?
                            $result['ax'] = (bool)strpos($content, '<Type>http://openid.net/srv/ax/1.0</Type>');
                            $result['sreg'] = strpos($content, '<Type>http://openid.net/sreg/1.0</Type>')
                                || strpos($content, '<Type>http://openid.net/extensions/sreg/1.1</Type>');

                            $server = $server[1];
                            if (isset($delegate[2])) {
                                $result['identity'] = trim($delegate[2]);
                            }

                            $result['url'] = $server;
                            $result['version'] = 2;

                            return $result;
                        }

                        // OpenID 1.1
                        $ns = preg_quote('http://openid.net/signon/1.1', '#');
                        if (preg_match('#<Type>\s*' . $ns . '\s*</Type>#s', $content)) {
                            preg_match('#<URI.*?>(.*)</URI>#', $content, $server);
                            preg_match('#<.*?Delegate>(.*)</.*?Delegate>#', $content, $delegate);
                            if (empty($server)) {
                                throw new \RuntimeException('No servers found!');
                            }
                            // AX can be used only with OpenID 2.0, so checking only SREG
                            $result['sreg'] = strpos($content, '<Type>http://openid.net/sreg/1.0</Type>')
                                || strpos($content, '<Type>http://openid.net/extensions/sreg/1.1</Type>');

                            $server = $server[1];
                            if (isset($delegate[1])) {
                                $result['identity'] = $delegate[1];
                            }

                            $result['url'] = $server;
                            $result['version'] = 1;

                            return $result;
                        }
                    }

                    $next = true;
                    $yadis = false;
                    $url = $originalUrl;
                    $content = null;
                    break;
                }
                if ($next) {
                    continue;
                }

                // There are no relevant information in headers, so we search the body.
                $content = $this->sendRequest(
                    $request->withMethod('GET')->withUri($request->getUri()->withPath($url))
                )->getBody()->getContents();

                $location = $this->extractHtmlTagValue($content, 'meta', 'http-equiv', 'X-XRDS-Location', 'content');
                if ($location) {
                    $url = $this->buildUrl($url, $location);
                    continue;
                }
            }

            if (!isset($content)) {
                $request = $this->createRequest('GET', $url);
                $content = $this->sendRequest($request);
            }

            // At this point, the YADIS Discovery has failed, so we'll switch to openid2 HTML discovery, then fallback to openid 1.1 discovery.
            $server = $this->extractHtmlTagValue($content, 'link', 'rel', 'openid2.provider', 'href');
            if (!$server) {
                // The same with openid 1.1
                $server = $this->extractHtmlTagValue($content, 'link', 'rel', 'openid.server', 'href');
                $delegate = $this->extractHtmlTagValue($content, 'link', 'rel', 'openid.delegate', 'href');
                $version = 1;
            } else {
                $delegate = $this->extractHtmlTagValue($content, 'link', 'rel', 'openid2.local_id', 'href');
                $version = 2;
            }

            if ($server) {
                // We found an OpenID2 OP Endpoint
                if ($delegate) {
                    // We have also found an OP-Local ID.
                    $result['identity'] = $delegate;
                }
                $result['url'] = $server;
                $result['version'] = $version;

                return $result;
            }
            throw new \RuntimeException('No servers found!');
        }
        throw new \RuntimeException('Endless redirection!');
    }

    /**
     * Composes SREG request parameters.
     * @return array SREG parameters.
     */
    protected function buildSregParams(): array
    {
        $params = [];
        /* We always use SREG 1.1, even if the server is advertising only support for 1.0.
        That's because it's fully backwards compatible with 1.0, and some providers
        advertise 1.0 even if they accept only 1.1. One such provider is myopenid.com */
        $params['openid.ns.sreg'] = 'http://openid.net/extensions/sreg/1.1';
        if (!empty($this->requiredAttributes)) {
            $params['openid.sreg.required'] = [];
            foreach ($this->requiredAttributes as $required) {
                if (!isset($this->axToSregMap[$required])) {
                    continue;
                }
                $params['openid.sreg.required'][] = $this->axToSregMap[$required];
            }
            $params['openid.sreg.required'] = implode(',', $params['openid.sreg.required']);
        }

        if (!empty($this->optionalAttributes)) {
            $params['openid.sreg.optional'] = [];
            foreach ($this->optionalAttributes as $optional) {
                if (!isset($this->axToSregMap[$optional])) {
                    continue;
                }
                $params['openid.sreg.optional'][] = $this->axToSregMap[$optional];
            }
            $params['openid.sreg.optional'] = implode(',', $params['openid.sreg.optional']);
        }

        return $params;
    }

    /**
     * Composes AX request parameters.
     * @return array AX parameters.
     */
    protected function buildAxParams(): array
    {
        $params = [];
        if (!empty($this->requiredAttributes) || !empty($this->optionalAttributes)) {
            $params['openid.ns.ax'] = 'http://openid.net/srv/ax/1.0';
            $params['openid.ax.mode'] = 'fetch_request';
            $aliases = [];
            $counts = [];
            $requiredAttributes = [];
            $optionalAttributes = [];
            foreach (['requiredAttributes', 'optionalAttributes'] as $type) {
                foreach ($this->$type as $alias => $field) {
                    if (is_int($alias)) {
                        $alias = strtr($field, '/', '_');
                    }
                    $aliases[$alias] = 'http://axschema.org/' . $field;
                    if (empty($counts[$alias])) {
                        $counts[$alias] = 0;
                    }
                    ++$counts[$alias];
                    ${$type}[] = $alias;
                }
            }
            foreach ($aliases as $alias => $ns) {
                $params['openid.ax.type.' . $alias] = $ns;
            }
            foreach ($counts as $alias => $count) {
                if ($count == 1) {
                    continue;
                }
                $params['openid.ax.count.' . $alias] = $count;
            }

            // Don't send empty ax.required and ax.if_available.
            // Google and possibly other providers refuse to support ax when one of these is empty.
            if (!empty($requiredAttributes)) {
                $params['openid.ax.required'] = implode(',', $requiredAttributes);
            }
            if (!empty($optionalAttributes)) {
                $params['openid.ax.if_available'] = implode(',', $optionalAttributes);
            }
        }

        return $params;
    }

    /**
     * Builds authentication URL for the protocol version 1.
     * @param ServerRequestInterface $incomingRequest
     * @param array $serverInfo OpenID server info.
     * @return string authentication URL.
     */
    protected function buildAuthUrlV1(ServerRequestInterface $incomingRequest, array $serverInfo)
    {
        $returnUrl = $this->getReturnUrl($incomingRequest);
        /* If we have an openid.delegate that is different from our claimed id,
        we need to somehow preserve the claimed id between requests.
        The simplest way is to just send it along with the return_to url.*/
        if ($serverInfo['identity'] !== $this->getClaimedId()) {
            $returnUrl .= (strpos($returnUrl, '?') ? '&' : '?') . 'openid.claimed_id=' . $this->getClaimedId();
        }

        $params = array_merge(
            [
                'openid.return_to' => $returnUrl,
                'openid.mode' => 'checkid_setup',
                'openid.identity' => $serverInfo['identity'],
                'openid.trust_root' => $this->trustRoot,
            ],
            $this->buildSregParams()
        );

        return $this->buildUrl($serverInfo['url'], ['query' => http_build_query($params, '', '&')]);
    }

    /**
     * Builds authentication URL for the protocol version 2.
     * @param ServerRequestInterface $incomingRequest
     * @param array $serverInfo OpenID server info.
     * @return string authentication URL.
     */
    protected function buildAuthUrlV2(ServerRequestInterface $incomingRequest, array $serverInfo)
    {
        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $this->getReturnUrl($incomingRequest),
            'openid.realm' => $this->getTrustRoot(),
        ];
        if ($serverInfo['ax']) {
            $params = array_merge($params, $this->buildAxParams());
        }
        if ($serverInfo['sreg']) {
            $params = array_merge($params, $this->buildSregParams());
        }
        if (!$serverInfo['ax'] && !$serverInfo['sreg']) {
            // If OP doesn't advertise either SREG, nor AX, let's send them both in worst case we don't get anything in return.
            $params = array_merge($this->buildSregParams(), $this->buildAxParams(), $params);
        }

        if ($serverInfo['identifier_select']) {
            $url = 'http://specs.openid.net/auth/2.0/identifier_select';
            $params['openid.identity'] = $url;
            $params['openid.claimed_id'] = $url;
        } else {
            $params['openid.identity'] = $serverInfo['identity'];
            $params['openid.claimed_id'] = $this->getClaimedId();
        }

        return $this->buildUrl($serverInfo['url'], ['query' => http_build_query($params, '', '&')]);
    }

    /**
     * Returns authentication URL. Usually, you want to redirect your user to it.
     * @param ServerRequestInterface $incomingRequest
     * @param bool $identifierSelect whether to request OP to select identity for an user in OpenID 2, does not affect OpenID 1.
     * @return string the authentication URL.
     */
    public function buildAuthUrl(ServerRequestInterface $incomingRequest, ?bool $identifierSelect = null)
    {
        $authUrl = $this->authUrl;
        $claimedId = $this->getClaimedId();
        if (empty($claimedId)) {
            $this->setClaimedId($authUrl);
        }
        $serverInfo = $this->discover($authUrl);
        if ($serverInfo['version'] == 2) {
            if ($identifierSelect !== null) {
                $serverInfo['identifier_select'] = $identifierSelect;
            }

            return $this->buildAuthUrlV2($incomingRequest, $serverInfo);
        }

        return $this->buildAuthUrlV1($incomingRequest, $serverInfo);
    }

    /**
     * Performs OpenID verification with the OP.
     * @param bool $validateRequiredAttributes whether to validate required attributes.
     * @return bool whether the verification was successful.
     */
    public function validate(bool $validateRequiredAttributes = true): bool
    {
        $claimedId = $this->getClaimedId();
        if (empty($claimedId)) {
            return false;
        }
        $params = [
            'openid.assoc_handle' => $this->data['openid_assoc_handle'],
            'openid.signed' => $this->data['openid_signed'],
            'openid.sig' => $this->data['openid_sig'],
        ];

        if (isset($this->data['openid_ns'])) {
            /* We're dealing with an OpenID 2.0 server, so let's set an ns
            Even though we should know location of the endpoint,
            we still need to verify it by discovery, so $server is not set here*/
            $params['openid.ns'] = 'http://specs.openid.net/auth/2.0';
        } elseif (isset($this->data['openid_claimed_id']) && $this->data['openid_claimed_id'] != $this->data['openid_identity']) {
            // If it's an OpenID 1 provider, and we've got claimed_id,
            // we have to append it to the returnUrl, like authUrlV1 does.
            $this->returnUrl .= (strpos($this->returnUrl, '?') ? '&' : '?') . 'openid.claimed_id=' . $claimedId;
        }

        if (!$this->compareUrl($this->data['openid_return_to'], $this->returnUrl)) {
            // The return_to url must match the url of current request.
            return false;
        }

        $serverInfo = $this->discover($claimedId);

        foreach (explode(',', $this->data['openid_signed']) as $item) {
            $value = $this->data['openid_' . str_replace('.', '_', $item)];
            $params['openid.' . $item] = $value;
        }

        $params['openid.mode'] = 'check_authentication';
        $request = $this->createRequest('POST', $serverInfo['url']);
        $request->getBody()->write((string)$params);

        $response = $this->sendRequest($request);

        if (preg_match('/is_valid\s*:\s*true/i', $response)) {
            if ($validateRequiredAttributes) {
                return $this->validateRequiredAttributes();
            }

            return true;
        }

        return false;
    }

    /**
     * Checks if all required attributes are present in the server response.
     * @return bool whether all required attributes are present.
     */
    protected function validateRequiredAttributes(): bool
    {
        if (!empty($this->requiredAttributes)) {
            $attributes = $this->fetchAttributes();
            foreach ($this->requiredAttributes as $openIdAttributeName) {
                if (!isset($attributes[$openIdAttributeName])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Gets AX attributes provided by OP.
     * @return array array of attributes.
     */
    protected function fetchAxAttributes(): array
    {
        $alias = null;
        if (isset($this->data['openid_ns_ax']) && $this->data['openid_ns_ax'] !== 'http://openid.net/srv/ax/1.0') {
            // It's the most likely case, so we'll check it before
            $alias = 'ax';
        } else {
            // 'ax' prefix is either undefined, or points to another extension, so we search for another prefix
            foreach ($this->data as $key => $value) {
                if ($value === 'http://openid.net/srv/ax/1.0' && strncmp($key, 'openid_ns_', 10) === 0) {
                    $alias = substr($key, strlen('openid_ns_'));
                    break;
                }
            }
        }
        if (!$alias) {
            // An alias for AX schema has not been found, so there is no AX data in the OP's response
            return [];
        }

        $attributes = [];
        foreach ($this->data as $key => $value) {
            $keyMatch = 'openid_' . $alias . '_value_';
            if (strncmp($key, $keyMatch, strlen($keyMatch))) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if (!isset($this->data['openid_' . $alias . '_type_' . $key])) {
                /* OP is breaking the spec by returning a field without
                associated ns. This shouldn't happen, but it's better
                to check, than cause an E_NOTICE.*/
                continue;
            }
            $key = substr($this->data['openid_' . $alias . '_type_' . $key], strlen('http://axschema.org/'));
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Gets SREG attributes provided by OP. SREG names will be mapped to AX names.
     * @return array array of attributes with keys being the AX schema names, e.g. 'contact/email'
     */
    protected function fetchSregAttributes()
    {
        $attributes = [];
        $sregToAx = array_flip($this->axToSregMap);
        foreach ($this->data as $key => $value) {
            $keyMatch = 'openid_sreg_';
            if (strncmp($key, $keyMatch, strlen($keyMatch))) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if (!isset($sregToAx[$key])) {
                // The field name isn't part of the SREG spec, so we ignore it.
                continue;
            }
            $attributes[$sregToAx[$key]] = $value;
        }

        return $attributes;
    }

    /**
     * Gets AX/SREG attributes provided by OP. Should be used only after successful validation.
     * Note that it does not guarantee that any of the required/optional parameters will be present,
     * or that there will be no other attributes besides those specified.
     * In other words. OP may provide whatever information it wants to.
     * SREG names will be mapped to AX names.
     * @return array array of attributes with keys being the AX schema names, e.g. 'contact/email'
     * @see http://www.axschema.org/types/
     */
    public function fetchAttributes(): array
    {
        if (isset($this->data['openid_ns']) && $this->data['openid_ns'] === 'http://specs.openid.net/auth/2.0') {
            // OpenID 2.0
            // We search for both AX and SREG attributes, with AX taking precedence.
            return array_merge($this->fetchSregAttributes(), $this->fetchAxAttributes());
        }

        return $this->fetchSregAttributes();
    }

    protected function initUserAttributes(): array
    {
        return array_merge(['id' => $this->getClaimedId()], $this->fetchAttributes());
    }

    /**
     * Compares 2 URLs taking in account possible GET parameters order miss match and URL encoding inconsistencies.
     * @param string $expectedUrl expected URL.
     * @param string $actualUrl actual URL.
     * @return bool whether URLs are equal.
     */
    protected function compareUrl(string $expectedUrl, string $actualUrl): bool
    {
        $expectedUrlInfo = parse_url($expectedUrl);
        $actualUrlInfo = parse_url($actualUrl);
        foreach ($expectedUrlInfo as $name => $expectedValue) {
            if ($name === 'query') {
                parse_str($expectedValue, $expectedUrlParams);
                parse_str($actualUrlInfo[$name], $actualUrlParams);
                $paramsDiff = array_diff_assoc($expectedUrlParams, $actualUrlParams);
                if (!empty($paramsDiff)) {
                    return false;
                }
            } elseif ($expectedValue != $actualUrlInfo[$name]) {
                return false;
            }
        }
        return true;
    }

    public function getName(): string
    {
        return (new Inflector())->pascalCaseToId(StringHelper::baseName(get_class($this)));
    }

    public function getTitle(): string
    {
        return StringHelper::baseName(get_class($this));
    }
}
