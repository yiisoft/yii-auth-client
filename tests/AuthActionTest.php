<?php

namespace yii\authclient\tests;

use yii\authclient\AuthAction;
use yii\web\Controller;

class AuthActionTest extends \yii\tests\TestCase
{
    protected function setUp()
    {
        $services = [
            'user' => [
                '__class' => \yii\web\User::class,
                'identityClass' => \yii\web\IdentityInterface::class,
            ],
            'request' => [
                '__class' => \yii\web\Request::class,
                'cookieValidationKey' => 'wefJDF8sfdsfSDefwqdxj9oq',
                'hostInfo' => 'http://testdomain.com',
                'scriptUrl' => '/index.php',
            ],
        ];
        $this->mockWebApplication([], null, $services);
    }

    // Tests :

    public function testSetGet()
    {
        $action = $this->createAction();
        $successUrl = 'http://test.success.url';
        $action->setSuccessUrl($successUrl);
        $this->assertEquals($successUrl, $action->getSuccessUrl(), 'Unable to setup success URL!');

        $cancelUrl = 'http://test.cancel.url';
        $action->setCancelUrl($cancelUrl);
        $this->assertEquals($cancelUrl, $action->getCancelUrl(), 'Unable to setup cancel URL!');
    }

    /**
     * @depends testSetGet
     */
    public function testGetDefaultSuccessUrl()
    {
        $action = $this->createAction();

        $this->assertNotEmpty($action->getSuccessUrl(), 'Unable to get default success URL!');
    }

    /**
     * @depends testSetGet
     */
    public function testGetDefaultCancelUrl()
    {
        $action = $this->createAction();

        $this->assertNotEmpty($action->getSuccessUrl(), 'Unable to get default cancel URL!');
    }

    public function testRedirect()
    {
        $action = $this->createAction();

        $url = 'http://test.url';
        $response = $action->redirect($url, true);

        $this->assertContains($url, $response->content);
    }

    protected function createAction()
    {
        $controller = new Controller(null, $this->app);

        return new AuthAction(null, $controller);
    }
}
