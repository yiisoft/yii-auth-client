# Upgrading Instructions for Yii Framework external authentication via OAuth and OpenID Extension

!!!IMPORTANT!!!

The following upgrading instructions are cumulative. That is,
if you want to upgrade from version A to version C and there is
version B between A and C, you need to following the instructions
for both A and B.

# Upgrade from yii2-authclient

* The signature of the `Yiisoft\Yii\AuthClient\BaseOAuth::saveAccessToken()` method has been changed.
  In case you are extending related class and override this method, you should check, if it matches parent declaration.
