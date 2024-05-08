Quick Start
===========

## Adding action to controller

Next step is to add [[Yiisoft\Yii\AuthClient\AuthAction]] to a web controller and provide a `successCallback` implementation,
which is suitable for your needs. Typically final controller code may look like following:

```php
use app\components\AuthHandler;

class SiteController extends Controller
{
    public function actions()
    {
        return [
            'auth' => [
                'class' => \Yiisoft\Yii\AuthClient\AuthAction::class,
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function onAuthSuccess($client)
    {
        (new AuthHandler($client))->handle();
    }
}
```

Note that it's important for `auth` action to be public accessible, so make sure it's not denied by access control filter.

Where AuthHandler implementation could be like this:

```php
<?php
namespace app\components;

use app\models\Auth;
use app\models\User;
use Yii;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Arrays\ArrayHelper;

/**
 * AuthHandler handles successful authentication via Yii auth component
 */
class AuthHandler
{
    /**
     * @var AuthClientInterface
     */
    private $client;

    public function __construct(AuthClientInterface $client)
    {
        $this->client = $client;
    }

    public function handle()
    {
        $attributes = $this->client->getUserAttributes();
        $email = ArrayHelper::getValue($attributes, 'email');
        $id = ArrayHelper::getValue($attributes, 'id');
        $nickname = ArrayHelper::getValue($attributes, 'login');

        /* @var Auth $auth */
        $auth = Auth::find()->where([
            'source' => $this->client->getId(),
            'source_id' => $id,
        ])->one();

        if (Yii::getApp()->user->isGuest) {
            if ($auth) { // login
                /* @var User $user */
                $user = $auth->user;
                $this->updateUserInfo($user);
                Yii::getApp()->user->login($user, Yii::getApp()->params['user.rememberMeDuration']);
            } else { // signup
                if ($email !== null && User::find()->where(['email' => $email])->exists()) {
                    Yii::getApp()->getSession()->setFlash('error', [
                        Yii::t('app', "User with the same email as in {client} account already exists but isn't linked to it. Login using email first to link it.", ['client' => $this->client->getTitle()]),
                    ]);
                } else {
                    $password = Yii::getApp()->security->generateRandomString(6);
                    $user = new User([
                        'username' => $nickname,
                        'github' => $nickname,
                        'email' => $email,
                        'password' => $password,
                    ]);
                    $user->generateAuthKey();
                    $user->generatePasswordResetToken();

                    $transaction = User::getDb()->beginTransaction();

                    if ($user->save()) {
                        $auth = new Auth([
                            'user_id' => $user->id,
                            'source' => $this->client->getId(),
                            'source_id' => (string)$id,
                        ]);
                        if ($auth->save()) {
                            $transaction->commit();
                            Yii::getApp()->user->login($user, Yii::getApp()->params['user.rememberMeDuration']);
                        } else {
                            Yii::getApp()->getSession()->setFlash('error', [
                                Yii::t('app', 'Unable to save {client} account: {errors}', [
                                    'client' => $this->client->getTitle(),
                                    'errors' => json_encode($auth->getErrors()),
                                ]),
                            ]);
                        }
                    } else {
                        Yii::getApp()->getSession()->setFlash('error', [
                            Yii::t('app', 'Unable to save user: {errors}', [
                                'client' => $this->client->getTitle(),
                                'errors' => json_encode($user->getErrors()),
                            ]),
                        ]);
                    }
                }
            }
        } else { // user already logged in
            if (!$auth) { // add auth provider
                $auth = new Auth([
                    'user_id' => Yii::getApp()->user->id,
                    'source' => $this->client->getId(),
                    'source_id' => (string)$attributes['id'],
                ]);
                if ($auth->save()) {
                    /** @var User $user */
                    $user = $auth->user;
                    $this->updateUserInfo($user);
                    Yii::getApp()->getSession()->setFlash('success', [
                        Yii::t('app', 'Linked {client} account.', [
                            'client' => $this->client->getTitle()
                        ]),
                    ]);
                } else {
                    Yii::getApp()->getSession()->setFlash('error', [
                        Yii::t('app', 'Unable to link {client} account: {errors}', [
                            'client' => $this->client->getTitle(),
                            'errors' => json_encode($auth->getErrors()),
                        ]),
                    ]);
                }
            } else { // there's existing auth
                Yii::getApp()->getSession()->setFlash('error', [
                    Yii::t('app',
                        'Unable to link {client} account. There is another user using it.',
                        ['client' => $this->client->getTitle()]),
                ]);
            }
        }
    }

    /**
     * @param User $user
     */
    private function updateUserInfo(User $user)
    {
        $attributes = $this->client->getUserAttributes();
        $github = ArrayHelper::getValue($attributes, 'login');
        if ($user->github === null && $github) {
            $user->github = $github;
            $user->save();
        }
    }
}
```

`successCallback` method is called when user was successfully authenticated via external service. Via `$client` instance
we can retrieve information received. In our case we'd like to:
 
- If user is guest and record found in auth then log this user in.
- If user is guest and record not found in auth then create new user and make a record in auth table. Then log in.
- If user is logged in and record not found in auth then try connecting additional account (save its data into auth table).

> Note: different Auth clients may require different approaches while handling authentication success. For example: Twitter
  does not allow returning of the user email, so you have to deal with this somehow.

### Auth client basic structure

Although, all clients are different they shares same basic interface [[Yiisoft\Yii\AuthClient\ClientInterface]],
which governs common API.

Each client has some descriptive data, which can be used for different purposes:

- `id` - unique client id, which separates it from other clients, it could be used in URLs, logs etc.
- `name` - external auth provider name, which this client is match too. Different auth clients
  can share the same name, if they refer to the same external auth provider.
  For example: clients for Google and Google Hybrid have same name "google".
  This attribute can be used inside the database, CSS styles and so on.
- `title` - user friendly name for the external auth provider, it is used to present auth client
  at the view layer.

Each auth client has different auth flow, but all of them supports `getUserAttributes()` method,
which can be invoked if authentication was successful.

This method allows you to get information about external user account, such as ID, email address,
full name, preferred language etc. Note that for each provider fields available may vary in both existence and
names.

Defining list of attributes, which external auth provider should return, depends on client type:

- [[Yiisoft\Yii\AuthClient\OpenId]]: combination of `requiredAttributes` and `optionalAttributes`.
- [[Yiisoft\Yii\AuthClient\OAuth1]] and [[Yiisoft\Yii\AuthClient\OAuth2]]: field `scope`, note that different
  providers use different formats for the scope.

> Tip: If you are using several different clients, you can unify the structure of the attributes, which they return,
  using [[Yiisoft\Yii\AuthClient\BaseClient::$normalizeUserAttributeMap]].


## Adding widget to login view

There's ready to use [[Yiisoft\Yii\AuthClient\Widgets\AuthChoice]] widget to use in views:

```php
<?= Yiisoft\Yii\AuthClient\Widgets\AuthChoice::widget([
     'baseAuthUrl' => ['site/auth'],
     'popupMode' => false,
]) ?>
```

