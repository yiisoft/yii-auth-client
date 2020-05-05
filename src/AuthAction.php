<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Yii\AuthClient\Exception\InvalidConfigException;
use Yiisoft\Yii\AuthClient\Exception\NotSupportedException;

/**
 * AuthAction performs authentication via different auth clients.
 * It supports [[OpenId]], [[OAuth1]] and [[OAuth2]] client types.
 *
 * Usage:
 *
 * ```php
 * class SiteController extends Controller
 * {
 *     public function actions()
 *     {
 *         return [
 *             'auth' => [
 *                 '__class' => \Yiisoft\Yii\AuthClient\AuthAction::class,
 *                 'successCallback' => [$this, 'successCallback'],
 *             ],
 *         ]
 *     }
 *
 *     public function successCallback($client)
 *     {
 *         $attributes = $client->getUserAttributes();
 *         // user login or signup comes here
 *     }
 * }
 * ```
 *
 * Usually authentication via external services is performed inside the popup window.
 * This action handles the redirection and closing of popup window correctly.
 *
 * @see Collection
 * @see \Yiisoft\Yii\AuthClient\Widget\AuthChoice
 */
final class AuthAction implements MiddlewareInterface
{
    /**
     * @var string name of the auth client collection application component.
     * It should point to {@see Collection} instance.
     */
    private string $clientCollection = 'authClientCollection';
    /**
     * @var string name of the GET param, which is used to passed auth client id to this action.
     * Note: watch for the naming, make sure you do not choose name used in some auth protocol.
     */
    private string $clientIdGetParamName = 'authclient';
    /**
     * @var callable PHP callback, which should be triggered in case of successful authentication.
     * This callback should accept {@see ClientInterface} instance as an argument.
     * For example:
     *
     * ```php
     * public function onAuthSuccess(ClientInterface $client)
     * {
     *     $attributes = $client->getUserAttributes();
     *     // user login or signup comes here
     * }
     * ```
     *
     * If this callback returns {@see ResponseInterface} instance, it will be used as action response,
     * otherwise redirection to {@see successUrl} will be performed.
     */
    private $successCallback;
    /**
     * @var callable PHP callback, which should be triggered in case of authentication cancellation.
     * This callback should accept {@see ClientInterface} instance as an argument.
     * For example:
     *
     * ```php
     * public function onAuthCancel(ClientInterface $client)
     * {
     *     // set flash, logging, etc.
     * }
     * ```
     *
     * If this callback returns {@see ResponseInterface} instance, it will be used as action response,
     * otherwise redirection to {@see cancelUrl} will be performed.
     */
    private $cancelCallback;
    /**
     * @var string name or alias of the view file, which should be rendered in order to perform redirection.
     * If not set - default one will be used.
     */
    private string $redirectView;

    /**
     * @var string the redirect url after successful authorization.
     */
    private string $successUrl;
    /**
     * @var string the redirect url after unsuccessful authorization (e.g. user canceled).
     */
    private string $cancelUrl;


    /**
     * @param string $url successful URL.
     */
    public function setSuccessUrl($url): void
    {
        $this->successUrl = $url;
    }

    /**
     * @return string successful URL.
     */
    public function getSuccessUrl(): string
    {
        if (empty($this->successUrl)) {
            $this->successUrl = $this->defaultSuccessUrl();
        }

        return $this->successUrl;
    }

    /**
     * @param string $url cancel URL.
     */
    public function setCancelUrl($url): void
    {
        $this->cancelUrl = $url;
    }

    /**
     * @return string cancel URL.
     */
    public function getCancelUrl(): string
    {
        if (empty($this->cancelUrl)) {
            $this->cancelUrl = $this->defaultCancelUrl();
        }

        return $this->cancelUrl;
    }

    /**
     * Creates default {@see successUrl} value.
     * @return string success URL value.
     */
    protected function defaultSuccessUrl(): string
    {
        return $this->app->getUser()->getReturnUrl();
    }

    /**
     * Creates default {@see cancelUrl} value.
     * @return string cancel URL value.
     */
    protected function defaultCancelUrl(): string
    {
        return Url::to($this->app->getUser()->loginUrl);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientId = $request->getAttribute($this->clientIdGetParamName);
        if (!empty($clientId)) {
            /* @var $collection Collection */
            $collection = $this->app->get($this->clientCollection);
            if (!$collection->hasClient($clientId)) {
                throw new NotFoundHttpException("Unknown auth client '{$clientId}'");
            }
            $client = $collection->getClient($clientId);

            return $this->auth($client);
        }

        throw new NotFoundHttpException();
    }

    /**
     * Perform authentication for the given client.
     * @param mixed $client auth client instance.
     * @return ResponseInterface response instance.
     * @throws NotSupportedException on invalid client.
     * @throws InvalidConfigException
     */
    protected function auth($client): ResponseInterface
    {
        if ($client instanceof OAuth2) {
            return $this->authOAuth2($client);
        } elseif ($client instanceof OAuth1) {
            return $this->authOAuth1($client);
        } elseif ($client instanceof OpenIdConnect) {
            return $this->authOpenId($client);
        }

        throw new NotSupportedException('Provider "' . get_class($client) . '" is not supported.');
    }

    /**
     * This method is invoked in case of successful authentication via auth client.
     * @param ClientInterface $client auth client instance.
     * @return ResponseInterface response instance.
     * @throws InvalidConfigException on invalid success callback.
     */
    protected function authSuccess($client): ResponseInterface
    {
        if (!is_callable($this->successCallback)) {
            throw new InvalidConfigException(
                '"' . get_class($this) . '::$successCallback" should be a valid callback.'
            );
        }

        $response = call_user_func($this->successCallback, $client);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return $this->redirectSuccess();
    }

    /**
     * This method is invoked in case of authentication cancellation.
     * @param ClientInterface $client auth client instance.
     * @return ResponseInterface response instance.
     */
    protected function authCancel($client): ResponseInterface
    {
        if ($this->cancelCallback !== null) {
            $response = call_user_func($this->cancelCallback, $client);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        return $this->redirectCancel();
    }

    /**
     * Redirect to the given URL or simply close the popup window.
     * @param mixed $url URL to redirect, could be a string or array config to generate a valid URL.
     * @param bool $enforceRedirect indicates if redirect should be performed even in case of popup window.
     * @return ResponseInterface response instance.
     */
    public function redirect($url, $enforceRedirect = true): ResponseInterface
    {
        $viewFile = $this->redirectView;
        if ($viewFile === null) {
            $viewFile = __DIR__ . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . 'redirect.php';
        } else {
            $viewFile = $this->app->getAlias($viewFile);
        }

        $viewData = [
            'url' => $url,
            'enforceRedirect' => $enforceRedirect,
        ];

        $response = $this->app->getResponse();
        $response->content = $this->app->getView()->renderFile($viewFile, $viewData);

        return $response;
    }

    /**
     * Redirect to the URL. If URL is null, {@see successUrl} will be used.
     * @param string $url URL to redirect.
     * @return ResponseInterface response instance.
     */
    public function redirectSuccess(?string $url = null): ResponseInterface
    {
        if ($url === null) {
            $url = $this->getSuccessUrl();
        }
        return $this->redirect($url);
    }

    /**
     * Redirect to the {@see cancelUrl} or simply close the popup window.
     * @param string $url URL to redirect.
     * @return ResponseInterface response instance.
     */
    public function redirectCancel(?string $url = null): ResponseInterface
    {
        if ($url === null) {
            $url = $this->getCancelUrl();
        }
        return $this->redirect($url, false);
    }

    /**
     * Performs OpenID auth flow.
     * @param OpenIdConnect $client auth client instance.
     * @return ResponseInterface action response.
     * @throws InvalidConfigException
     */
    protected function authOpenId(OpenIdConnect $client): ResponseInterface
    {
        $request = $this->app->getRequest();
        $mode = $request->get('openid_mode', $request->post('openid_mode'));

        if (empty($mode)) {
            $url = $client->buildAuthUrl();
            return $this->app->getResponse()->redirect($url);
        }

        switch ($mode) {
            case 'id_res':
                if ($client->validate()) {
                    return $this->authSuccess($client);
                }
                throw new HttpException(
                    400,
                    'Unable to complete the authentication because the required data was not received.'
                );
            case 'cancel':
                return $this->authCancel($client);
            default:
                throw new HttpException(400);
        }
    }

    /**
     * Performs OAuth1 auth flow.
     * @param OAuth1 $client auth client instance.
     * @return ResponseInterface action response.
     */
    protected function authOAuth1(OAuth1 $client): ResponseInterface
    {
        $request = $this->app->getRequest();

        // user denied error
        if ($request->get('denied') !== null) {
            return $this->authCancel($client);
        }

        if (($oauthToken = $request->get('oauth_token', $request->post('oauth_token'))) !== null) {
            // Upgrade to access token.
            $client->fetchAccessToken($oauthToken);
            return $this->authSuccess($client);
        }

        // Get request token.
        $requestToken = $client->fetchRequestToken();
        // Get authorization URL.
        $url = $client->buildAuthUrl($requestToken);
        // Redirect to authorization URL.
        return $this->app->getResponse()->redirect($url);
    }

    /**
     * Performs OAuth2 auth flow.
     * @param OAuth2 $client auth client instance.
     * @return ResponseInterface action response.
     * @throws Exception on failure.
     */
    protected function authOAuth2(OAuth2 $client): ResponseInterface
    {
        $request = $this->app->getRequest();

        if (($error = $request->get('error')) !== null) {
            if ($error === 'access_denied') {
                // user denied error
                return $this->authCancel($client);
            }
            // request error
            $errorMessage = $request->get('error_description', $request->get('error_message'));
            if ($errorMessage === null) {
                $errorMessage = http_build_query($request->get());
            }
            throw new Exception('Auth error: ' . $errorMessage);
        }

        // Get the access_token and save them to the session.
        if (($code = $request->get('code')) !== null) {
            $token = $client->fetchAccessToken($code);
            if (!empty($token)) {
                return $this->authSuccess($client);
            }
            return $this->authCancel($client);
        }

        $url = $client->buildAuthUrl();
        return $this->app->getResponse()->redirect($url);
    }
}
