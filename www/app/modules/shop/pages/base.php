<?php

namespace App\Modules\Shop\Pages;

use App\Application as App;
use App\Helper;
use App\System;

class Base extends \Zippy\Html\WebPage
{

    public function __construct($params = null) {

        \Zippy\Html\WebPage::__construct();

        $shop = System::getOptions("shop");
        if (!is_array($shop)) {
            $shop = array();
        }
        $user = System::getUser();
        if ($shop["uselogin"] == 1) {
            if ($user->user_id == 0) {
                App::Redirect("\\App\\Modules\\Shop\\Pages\\Userlogin");
                return;

            }
        }


        //  $this->_tvars["islogined"] = $user->user_id > 0;
        $this->_tvars["currencyname"] = $shop["currencyname"];
        $this->_tvars["notcnt"] = false;

        $this->add(new \Zippy\Html\Link\BookmarkableLink('shopcart', "/index.php?p=/App/Modules/Shop/Pages/Order"))->setVisible(false);
        $this->add(new \Zippy\Html\Link\BookmarkableLink('showcompare', "/index.php?p=/App/Modules/Shop/Pages/Compare"))->setVisible(false);

        $this->op = System::getOptions("shop");

        $this->add(new \Zippy\Html\Link\BookmarkableLink('logo', "/"))->setVisible(strlen($this->op['logo']) > 0);
        $this->logo->setValue($this->op['logo']);

    }

    //вывод ошибки,  используется   в дочерних страницах
    public function setError($msg, $p1 = "", $p2 = "") {
        $msg = Helper::l($msg, $p1, $p2);
        System::setErrorMsg($msg);
    }

    public function setSuccess($msg, $p1 = "", $p2 = "") {
        $msg = Helper::l($msg, $p1, $p2);
        System::setSuccessMsg($msg);
    }

    public function setWarn($msg, $p1 = "", $p2 = "") {
        $msg = Helper::l($msg, $p1, $p2);
        System::setWarnMsg($msg);
    }

    public function setInfo($msg, $p1 = "", $p2 = "") {
        $msg = Helper::l($msg, $p1, $p2);
        System::setInfoMsg($msg);
    }

    final protected function isError() {
        return strlen(System::getErrorMsg()) > 0;
    }

    protected function beforeRender() {
        $basket = \App\Modules\Shop\Basket::getBasket();
        $this->shopcart->setVisible($basket->isEmpty() == false);
        $this->showcompare->setVisible(\App\Modules\Shop\CompareList::getCompareList()->isEmpty() == false);

        $this->_tvars["notcnt"] = $basket->getItemCount();


    }

    protected function afterRender() {
        if (strlen(System::getErrorMsg()) > 0) {
            App::$app->getResponse()->addJavaScript("toastr.error('" . System::getErrorMsg() . "')        ", true);
        }
        if (strlen(System::getWarnMsg()) > 0) {
            App::$app->getResponse()->addJavaScript("toastr.warning('" . System::getWarnMsg() . "')        ", true);
        }
        if (strlen(System::getSuccesMsg()) > 0) {
            App::$app->getResponse()->addJavaScript("toastr.success('" . System::getSuccesMsg() . "')        ", true);
        }
        if (strlen(System::getInfoMsg()) > 0) {
            App::$app->getResponse()->addJavaScript("toastr.info('" . System::getInfoMsg() . "')        ", true);
        }


        $this->setError('');
        $this->setSuccess('');

        $this->setInfo('');
        $this->setWarn('');
    }

    //Перезагрузить страницу  с  клиента
    //например для  сброса  адресной строки  после  команды удаления
    protected final function resetURL() {
        \App\Application::$app->setReloadPage();
    }

}
