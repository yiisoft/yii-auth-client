Быстрый старт
===========

## Добавление экшена в контроллер

Следующий шаг заключается в добавлении [[Yiisoft\Yii\AuthClient\AuthAction]] в веб контроллер и обеспечении реализации 
`successCallback`, выполняющий ваши требования.



```php
class SiteController extends Controller
{
    public function actions()
    {
        return [
            'auth' => [
                'class' => Yiisoft\Yii\AuthClient\AuthAction::class,
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function onAuthSuccess($client)
    {
        $attributes = $client->getUserAttributes();

        /* @var $auth Auth */
        $auth = Auth::find()->where([
            'source' => $client->getId(),
            'source_id' => $attributes['id'],
        ])->one();
        
        if (Yii::getApp()->user->isGuest) {
            if ($auth) { // авторизация
                $user = $auth->user;
                Yii::getApp()->user->login($user);
            } else { // регистрация
                if (isset($attributes['email']) && User::find()->where(['email' => $attributes['email']])->exists()) {
                    Yii::getApp()->getSession()->setFlash('error', [
                        Yii::t('app', "Пользователь с такой электронной почтой как в {client} уже существует, но с ним не связан. Для начала войдите на сайт использую электронную почту, для того, что бы связать её.", ['client' => $client->getTitle()]),
                    ]);
                } else {
                    $password = Yii::getApp()->security->generateRandomString(6);
                    $user = new User([
                        'username' => $attributes['login'],
                        'email' => $attributes['email'],
                        'password' => $password,
                    ]);
                    $user->generateAuthKey();
                    $user->generatePasswordResetToken();
                    $transaction = $user->getDb()->beginTransaction();
                    if ($user->save()) {
                        $auth = new Auth([
                            'user_id' => $user->id,
                            'source' => $client->getId(),
                            'source_id' => (string)$attributes['id'],
                        ]);
                        if ($auth->save()) {
                            $transaction->commit();
                            Yii::getApp()->user->login($user);
                        } else {
                            print_r($auth->getErrors());
                        }
                    } else {
                        print_r($user->getErrors());
                    }
                }
            }
        } else { // Пользователь уже зарегистрирован
            if (!$auth) { // добавляем внешний сервис аутентификации
                $auth = new Auth([
                    'user_id' => Yii::getApp()->user->id,
                    'source' => $client->getId(),
                    'source_id' => $attributes['id'],
                ]);
                $auth->save();
            }
        }
    }
}
```

Метод `successCallback` вызывается, когда пользователь был успешно аутентифицирован через внешний сервис. Через
экземпляр `$client` мы можем извлечь полученную информацию. В нашем случае мы хотели бы:

- Если пользователь гость и в таблице auth существует запись, то проводим аутентификацию этого пользователя.
- Если пользователь гость и в таблице auth записи не существует, то создаём нового пользователя и запись в таблице auth. После
проводим аутентификацию пользователя.
- Если пользователь прошёл аутентификацию и запись в таблице auth не найдена, то пытаемся подключить дополнительный 
аккаунт (сохранить его данные в таблицу auth).

> Примечание: Могут потребоваться различные подходы обработки успешной аутентификации для различных клиентов 
аутентификации.
  Например: Twitter не допускает возвращение электронной почты пользователя, но так или иначе Вы должны с этим как то 
  работать.

### Базовая структура клиента аутентификации

Хоть все клиенты и разные, всё же они реализуют базовый интерфейс [[Yiisoft\Yii\AuthClient\ClientInterface]], который управляет 
общим API.

У каждого клиента есть некоторые описательные данные, которые могут использоваться в различных целях:

- `id` - уникальный идентификатор клиента, который отделяет его от других клиентов, может использоваться
  в URL'ах, логах и т.д.
- `name` - внешнее подлиное имя сервиса аутентификации, которое так же соответствует имени клиента. Различные
  клиенты аутентификации могут иметь одно и то же имя, если они относятся к одному и тому же внешнему сервису
  аутентификации.
  Например: клиенты для Google и Google Hybrid имеют одинаковое имя "google".
  Данный атрибут может быть использован внутри баз данных, CSS стилей и так далее.
- `title` - удобное для пользователя имя внешнего сервиса аутентификации, используется для предоставления клиента
  аутентификации на уровне представления.

Каждый клиент аутентификации имеет отличный от других процесс аутентификации, но каждый из них поддерживает метод 
`getUserAttributes()`, который может быть вызван, в случае, если аутентификация прошла успешно.

Это метод позволяет получить информацию о внешней учетной записи пользователя, такую, как ID, адрес электронной почты, 
полное имя, предпочитаемый язык и т.д. Обратите внимание, что для каждого внешнего сервиса в списке доступных полей 
может изменяться как название поля, так и сам факт его существования.

Определение списка атрибутов, возвращаемых внешним сервисом аутентификации, зависит от типа самого клиента:

- [[Yiisoft\Yii\AuthClient\OpenId]]: сочетание `requiredAttributes` и `optionalAttributes`.
- [[Yiisoft\Yii\AuthClient\OAuth1]] и [[Yiisoft\Yii\AuthClient\OAuth2]]: поле `scope`, обратите внимание, что разные сервисы используют 
разные форматы для scope.

> Совет: если Вы используете несколько различных клиентов, Вы можете объединить структуры атрибутов, которые они 
возвращают, при помощи[[Yiisoft\Yii\AuthClient\BaseClient::normalizeUserAttributeMap]].


## Добавление виджета в представление аутентификации

В представлениях можно использовать готовый виджет [[Yiisoft\Yii\AuthClient\Widgets\AuthChoice]]:

```php
<?= Yiisoft\Yii\AuthClient\Widgets\AuthChoice::widget([
     'baseAuthUrl' => ['site/auth'],
     'popupMode' => false,
]) ?>
```
