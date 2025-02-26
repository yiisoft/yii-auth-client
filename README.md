<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <a href="https://oauth.net/2/" target="_blank">
        <img src="https://oauth.net/images/oauth-2-sm.png" height="80px" alt="Oauth">
    </a>
    <h1 align="center">Yii External Authentication</h1>
    <br>
</p>

[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fyii-auth-client)](https://dashboard.stryker-mutator.io/reports/yii-auth-client)
[![Build status](https://github.com/rossaddison/yii-auth-client/workflows/build/badge.svg)](https://github.com/rossaddison/yii-auth-client/actions?query=workflow%3Abuild)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Frossaddison%2Fyii-auth-client%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/rossaddison/yii-auth-client/master)
[![static analysis](https://github.com/rossaddison/yii-auth-client/workflows/static%20analysis/badge.svg)](https://github.com/rossaddison/yii-auth-client/actions?query=workflow%3A%22static+analysis%22)
[![Psalm Level](https://img.shields.io/static/v1?label=Psalm%20Level&message=1&color=66ff00)](https://psalm.dev)
[![type-coverage](https://shepherd.dev/github/rossaddison/yii-auth-client/coverage.svg)](https://shepherd.dev/github/rossaddison/yii-auth-client)
[![Total Downloads](http://poser.pugx.org/rossaddison/yii-auth-client/downloads)](https://packagist.org/packages/rossaddison/yii-auth-client)

This extension adds [OAuth](https://oauth.net/), and [OAuth2](https://oauth.net/2/) 
consumers for the [Yii framework](https://www.yiiframework.com).

## Requirements

- PHP 8.3 or higher.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require rossaddison/yii-auth-client
```

## Documentation

26th November 2024
Purpose for this fork: Introduce external authentication into rossaddison/invoice
- Due to the archiving of nov/jose-php, openId Connect, and OAuth1 have been removed from this fork
  to allow the use and testing of other clients such as: 
    Facebook, Google, Github, LinkedIn, 
    Live, TwitterOAuth2, 
    VKontakt, and Yandex.
- This fork is being tested currently and is psalm level 1 statically built and tested only.
- 3 issue handlers are currently in the psalm.xml and can be removed independently to see relevant errors
  by running e.g c:\wamp64\www\yii-auth-client\php ./vendor/bin/psalm
- A suitable php substitute will hopefully be introduced later for nov/jose-php. yiisoft/auth-jwt is the obvious choice.
- Due to the archiving of yiisoft/yii-jquery,  a cdn for jquery has been introduced into 
  src/Asset/AuthChoiceAsset.php
- Guide: [English](docs/guide/en/README.md), [Русский](docs/guide/ru/README.md)
- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for that.
You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii External Authentication is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
