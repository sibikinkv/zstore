<?php

namespace App\Pages\Service;

use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Service;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;

/**
 * АРМ кассира
 */
class ARMPos extends \App\Pages\Base
{

    public  $_itemlist = array();
    public  $_serlist  = array();
    private $pos;
    private $_doc      = null;


    private $_pt       = 0;
    private $_store_id = 0;

    public function __construct() {
        parent::__construct();


        if (false == \App\ACL::checkShowSer('ARMPos')) {
            return;
        }

        //обшие настройки
        $this->add(new Form('form1'));
        $plist = \App\Entity\Pos::findArray('pos_name', '');

        $this->form1->add(new DropDownChoice('pos', $plist, 0));
        $this->form1->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));
        $this->form1->add(new DropDownChoice('pricetype', \App\Entity\Item::getPriceTypeList(), H::getDefPriceType()));

        $this->form1->add(new SubmitButton('next1'))->onClick($this, 'next1docOnClick');

        $this->add(new Form('form2'))->setVisible(false);

        //  ввод товаров

        $this->form2->add(new SubmitButton('next2'))->onClick($this, 'next2docOnClick');
        $this->form2->add(new TextInput('barcode'));
        $this->form2->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');
        $this->form2->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->form2->add(new SubmitLink('addser'))->onClick($this, 'addserOnClick');
        $this->form2->addser->setVisible(Service::findCnt('disabled<>1') > 0);  //показываем  если  есть  услуги
        $this->form2->add(new Label('total'));

        $this->form2->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'));
        $this->form2->add(new DataView('detailser', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_serlist')), $this, 'serOnRow'));
        $this->form2->add(new ClickLink('openshift', $this, 'OnOpenShift'));
        $this->form2->add(new ClickLink('closeshift', $this, 'OnCloseShift'));


        //оплата
        $this->add(new Form('form3'))->setVisible(false);
        $this->form3->add(new DropDownChoice('payment', \App\Entity\MoneyFund::getList(true, true), H::getDefMF()))->onChange($this, 'OnPayment');

        $this->form3->add(new TextInput('document_number'));

        $this->form3->add(new Date('document_date'))->setDate(time());
        $this->form3->add(new TextArea('notes'));
        $this->form3->add(new SubmitLink('addcust'))->onClick($this, 'addcustOnClick');


        $this->form3->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');
        $this->form3->customer->onChange($this, 'OnChangeCustomer');
        $this->form3->add(new Button('cancel2'))->onClick($this, 'cancel2docOnClick');
        $this->form3->add(new SubmitButton('save'))->onClick($this, 'savedocOnClick');
        $this->form3->add(new TextInput('total2'));
        $this->form3->add(new TextInput('paydisc'));
        $this->form3->add(new TextInput('payamount'));
        $this->form3->add(new TextInput('payed'));
        $this->form3->add(new TextInput('exchange'));

        $this->form3->add(new Label('discount'));
        //печать
        $this->add(new Form('form4'))->setVisible(false);
        $this->form4->add(new Label('showcheck'));
        $this->form4->add(new Button('newdoc'))->onClick($this, 'newdoc');
        $this->form4->add(new Button('print'));


        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new TextInput('editserial'));

        $this->editdetail->add(new AutocompleteTextInput('edittovar'))->onText($this, 'OnAutoItem');
        $this->editdetail->edittovar->onChange($this, 'OnChangeItem', true);

        $this->editdetail->add(new Label('qtystock'));

        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');


        $this->add(new Form('editserdetail'))->setVisible(false);
        $this->editserdetail->add(new TextInput('editserquantity'))->setText("1");
        $this->editserdetail->add(new TextInput('editserprice'));


        $this->editserdetail->add(new AutocompleteTextInput('editser'))->onText($this, 'OnAutoSer');
        $this->editserdetail->editser->onChange($this, 'OnChangeSer', true);

        $this->editserdetail->add(new Button('cancelser'))->onClick($this, 'cancelrowOnClick');
        $this->editserdetail->add(new SubmitButton('submitser'))->onClick($this, 'saveserOnClick');


        //добавление нового контрагента
        $this->add(new Form('editcust'))->setVisible(false);
        $this->editcust->add(new TextInput('editcustname'));
        $this->editcust->add(new TextInput('editphone'));
        $this->editcust->add(new Button('cancelcust'))->onClick($this, 'cancelcustOnClick');
        $this->editcust->add(new SubmitButton('savecust'))->onClick($this, 'savecustOnClick');

        /*
              //Закрытие  смены
              $this->add(new Form('zform'))->setVisible(false);
              $this->zform->add(new TextInput('zformqnt'));
              $this->zform->add(new TextInput('zformnal'));
              $this->zform->add(new TextInput('zformbnal'));
              $this->zform->add(new TextInput('zformcredit'));
              $this->zform->add(new TextInput('zformprepaid'));
              $this->zform->add(new TextInput('zformtotal'));
              $this->zform->add(new Button('cancelzform'))->onClick($this, 'cancelzformOnClick');
              $this->zform->add(new SubmitButton('savezform'))->onClick($this, 'savezformOnClick');

          */
    }


    public function cancel2docOnClick($sender) {

        $this->form2->setVisible(true);
        $this->form3->setVisible(false);
    }

    public function cancel3docOnClick($sender) {

        $this->form3->setVisible(true);
        $this->form4->setVisible(false);
    }

    public function next1docOnClick($sender) {
        $this->pos = \App\Entity\Pos::load($this->form1->pos->getValue());

        $this->_store_id = $this->form1->store->getValue();
        $this->_pt = $this->form1->pricetype->getValue();

        if ($this->pos == null) {
            $this->setError("noselterm");
            return;
        }

        if ($this->_store_id == 0) {
            $this->setError("noselstore");
            return;
        }

        if (strlen($this->_pt) == 0) {
            $this->setError("noselpricetype");
            return;
        }

        $this->form1->setVisible(false);
        $this->form2->setVisible(true);

        $this->newdoc(null);
    }

    public function newdoc($sender) {

        $this->_doc = \App\Entity\Doc\Document::create('POSCheck');

        $this->_itemlist = array();
        $this->_serlist = array();
        $this->form2->detail->Reload();
        $this->form2->detailser->Reload();
        $this->calcTotal();


        $this->form3->document_date->setDate(time());
        $this->form3->document_number->setText($this->_doc->nextNumber());
        $this->form3->customer->setKey(0);
        $this->form3->customer->setText('');
        $this->form3->paydisc->setText('0');
        $this->form3->payamount->setText('0');
        $this->form3->payed->setText('0');
        $this->form3->exchange->setText('0');
        $this->form3->discount->setText('');

        $this->form2->setVisible(true);
        $this->form4->setVisible(false);
    }

    public function next2docOnClick($sender) {
        if (count($this->_itemlist) == 0 && count($this->_serlist) == 0) {
            $this->setError('noenterpos');
            return;
        }

        $this->form1->setVisible(false);
        $this->form2->setVisible(false);
        $this->form3->setVisible(true);
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('tovar', $item->itemname));

        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('msr', $item->msr));
        $row->add(new Label('snumber', $item->snumber));
        $row->add(new Label('sdate', $item->sdate > 0 ? \App\Helper::fd($item->sdate) : ''));

        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));

        $row->add(new Label('amount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function serOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('service', $item->service_name));

        $row->add(new Label('serquantity', H::fqty($item->quantity)));
        $row->add(new Label('serprice', H::fa($item->price)));

        $row->add(new Label('seramount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('serdelete'))->onClick($this, 'serdeleteOnClick');
        //  $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
    }

    public function addcodeOnClick($sender) {
        $code = trim($this->form2->barcode->getText());
        $store = $this->form1->store->getValue();
        $this->form2->barcode->setText('');
        if ($code == '') {
            return;
        }


        $code_ = Item::qstr($code);
        $item = Item::getFirst(" item_id in(select item_id from store_stock where store_id={$store}) and  (item_code = {$code_} or bar_code = {$code_})");


        if ($item == null) {
            $this->setError("noitemcode", $code);
            return;
        }

        $qty = $item->getQuantity($store);
        if ($qty <= 0) {
            $this->setError("noitemonstore", $item->itemname);
        }


        if ($this->_itemlist[$item->item_id] instanceof Item) {
            $this->_itemlist[$item->item_id]->quantity += 1;
        } else {


            $price = $item->getPrice($this->pos->pricetype, $store);
            $item->price = $price;
            $item->quantity = 1;

            if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

                $serial = '';
                $slist = $item->getSerials($store);
                if (count($slist) == 1) {
                    $serial = array_pop($slist);
                }

                if (strlen($serial) == 0) {
                    $this->setWarn('needs_serial');
                    $this->editdetail->setVisible(true);
                    $this->form2->setVisible(false);


                    $this->editdetail->edittovar->setKey($item->item_id);
                    $this->editdetail->edittovar->setText($item->itemname);
                    $this->editdetail->editserial->setText('');
                    $this->editdetail->editquantity->setText('1');
                    $this->editdetail->editprice->setText($item->price);

                    return;
                } else {
                    $item->snumber = $serial;
                }
            }
            $this->_itemlist[$item->item_id] = $item;
        }
        $this->form2->detail->Reload();
        $this->calcTotal();
    }

    public function deleteOnClick($sender) {


        $tovar = $sender->owner->getDataItem();
        // unset($this->_itemlist[$tovar->tovar_id]);

        $this->_itemlist = array_diff_key($this->_itemlist, array($tovar->item_id => $this->_itemlist[$tovar->item_id]));
        $this->form2->detail->Reload();
        $this->calcTotal();
    }

    public function serdeleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }

        $ser = $sender->owner->getDataItem();
        // unset($this->_itemlist[$tovar->tovar_id]);

        $this->_serlist = array_diff_key($this->_serlist, array($ser->service_id => $this->_serlist[$ser->service_id]));
        $this->form2->detailser->Reload();
        $this->calcTotal();
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->editdetail->editquantity->setText("1");
        $this->editdetail->editprice->setText("0");
        $this->editdetail->qtystock->setText("");
        $this->form2->setVisible(false);
    }

    public function addserOnClick($sender) {
        $this->editserdetail->setVisible(true);
        $this->editserdetail->editserquantity->setText("1");
        $this->editserdetail->editserprice->setText("0");

        $this->form2->setVisible(false);
    }

    public function saverowOnClick($sender) {
        $store = $this->form1->store->getValue();

        $id = $this->editdetail->edittovar->getKey();
        if ($id == 0) {
            $this->setError("noselitem");
            return;
        }
        $item = Item::load($id);

        $item->quantity = $this->editdetail->editquantity->getText();
        $item->snumber = $this->editdetail->editserial->getText();
        $qstock = $this->editdetail->qtystock->getText();

        $item->price = $this->editdetail->editprice->getText();

        if ($item->quantity > $qstock) {
            $this->setWarn('inserted_extra_count');
        }

        if (strlen($item->snumber) == 0 && $item->useserial == 1 && $this->_tvars["usesnumber"] == true) {
            $this->setError("needs_serial");
            return;
        }

        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {
            $slist = $item->getSerials($store);

            if (in_array($item->snumber, $slist) == false) {
                $this->setWarn('invalid_serialno');
            }
        }


        $this->_itemlist[$item->item_id] = $item;
        $this->editdetail->setVisible(false);
        $this->form2->setVisible(true);

        $this->form2->detail->Reload();
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editserial->setText("");
        $this->calcTotal();
    }

    public function saveserOnClick($sender) {

        $id = $this->editserdetail->editser->getKey();
        if ($id == 0) {
            $this->setError("noselservice");
            return;
        }
        $ser = Service::load($id);

        $ser->quantity = $this->editserdetail->editserquantity->getText();

        $ser->price = $this->editserdetail->editserprice->getText();

        $this->_serlist[$ser->service_id] = $ser;
        $this->editserdetail->setVisible(false);
        $this->form2->setVisible(true);
        $this->form2->detailser->Reload();

        //очищаем  форму
        $this->editserdetail->editser->setKey(0);
        $this->editserdetail->editser->setText('');
        $this->editserdetail->editserquantity->setText("1");
        $this->editserdetail->editserprice->setText("");
        $this->calcTotal();
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->form2->setVisible(true);
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
    }

    private function calcTotal() {

        $total = 0;

        foreach ($this->_itemlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;
        }
        foreach ($this->_serlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;
        }
        $this->form2->total->setText(H::fa($total));
        $this->form3->total2->setText(H::fa($total));
        $this->form3->payamount->setText(H::fa($total));
    }

    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $item = Item::load($id);
        $store = $this->form1->store->getValue();


        $price = $item->getPrice($this->pos->pricetype, $store);
        $qty = $item->getQuantity($store);

        $this->editdetail->qtystock->setText(H::fqty($qty));
        $this->editdetail->editprice->setText($price);
        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

            $serial = '';
            $slist = $item->getSerials($store);
            if (count($slist) == 1) {
                $serial = array_pop($slist);
            }
            $this->editdetail->editserial->setText($serial);
        }


        $this->updateAjax(array('qtystock', 'editprice', 'editserial'));
    }

    public function OnAutoItem($sender) {

        $text = trim($sender->getText());
        return Item::findArrayAC($text);
    }

    public function OnAutoSer($sender) {

        $text = trim($sender->getText());
        $text = Service::qstr('%' . $text . '%');
        return Service::findArray('service_name', "disabled <> 1 and service_name like {$text}");
    }

    public function OnChangeSer($sender) {
        $id = $sender->getKey();
        $ser = Service::load($id);
        $this->editserdetail->editserprice->setText($ser->price);

        $this->updateAjax(array('editserprice'));
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    public function OnChangeCustomer($sender) {
        $this->form3->discount->setVisible(false);
        $total = $this->form3->total2->getText();
        $disc = 0;


        $customer_id = $this->form3->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);
            if ($customer->discount > 0) {
                $this->form3->discount->setText("Постоянная скидка " . $customer->discount . '%');
                $this->form3->discount->setVisible(true);
                $disc = round($total * ($customer->discount / 100));
            } else {
                if ($customer->bonus > 0) {
                    $this->form3->discount->setText("Бонусы " . $customer->bonus);
                    $this->form3->discount->setVisible(true);
                    if ($total >= $customer->bonus) {
                        $disc = $customer->bonus;
                    } else {
                        $disc = $total;
                    }
                }
            }
        }


        $this->form3->paydisc->setText(H::fa($disc));
        $this->form3->payamount->setText(H::fa($total - $disc));
    }

    //добавление нового контрагента
    public function addcustOnClick($sender) {
        $this->editcust->setVisible(true);
        $this->form3->setVisible(false);

        $this->editcust->editcustname->setText('');
        $this->editcust->editphone->setText('');
    }

    public function savecustOnClick($sender) {
        $custname = trim($this->editcust->editcustname->getText());
        if (strlen($custname) == 0) {
            $this->setError("entername");
            return;
        }
        $cust = new Customer();
        $cust->customer_name = $custname;
        $cust->phone = $this->editcust->editphone->getText();

        if (strlen($cust->phone) > 0 && strlen($cust->phone) != H::PhoneL()) {
            $this->setError("tel10", H::PhoneL());
            return;
        }

        $c = Customer::getByPhone($cust->phone);
        if ($c != null) {
            if ($c->customer_id != $cust->customer_id) {
                $this->setError("existcustphone");
                return;
            }
        }

        $cust->type = 1;
        $cust->save();
        $this->form3->customer->setText($cust->customer_name);
        $this->form3->customer->setKey($cust->customer_id);

        $this->editcust->setVisible(false);
        $this->form3->setVisible(true);
        $this->form3->discount->setVisible(false);
        $this->_discount = 0;
    }

    public function cancelcustOnClick($sender) {
        $this->editcust->setVisible(false);
        $this->_docform->setVisible(true);
    }

    public function savedocOnClick($sender) {

        $this->_doc->document_number = $this->form3->document_number->getText();

        $doc = Document::getFirst("   document_number = '{$this->_doc->document_number}' ");
        if ($doc instanceof Document) {   //если уже  кто то  сохранил  с таким номером
            $this->_doc->document_number = $this->_doc->nextNumber();
            $this->form3->document_number->setText($this->_doc->document_number);
        }
        if (false == $this->_doc->checkUniqueNumber()) {
            $this->_docform->document_number->setText($this->_doc->nextNumber());
            $this->setError('nouniquedocnumber_created');
            return;
        }
        $this->_doc->document_date = $this->form3->document_date->getDate();
        $this->_doc->notes = $this->form3->notes->getText();

        $this->_doc->customer_id = $this->form3->customer->getKey();
        $this->_doc->payamount = $this->form3->payamount->getText();

        $this->_doc->headerdata['time'] = time();
        $this->_doc->payed = $this->form3->payed->getText();
        $this->_doc->headerdata['exchange'] = $this->form3->exchange->getText();
        $this->_doc->headerdata['paydisc'] = $this->form3->paydisc->getText();
        $this->_doc->headerdata['payment'] = $this->form3->payment->getValue();

        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID) {
            $this->_doc->headerdata['paydisc'] = 0;
            $this->_doc->payed = 0;
            $this->_doc->payamount = 0;
        }
        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::CREDIT) {
            $this->_doc->payed = 0;
        }

        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID && $this->_doc->customer_id == 0) {
            $this->setError("mustsel_cust");
            return;
        }
        if ($this->_doc->payamount > $this->_doc->payed && $this->_doc->customer_id == 0) {
            $this->setError("mustsel_cust");
            return;
        }

        $this->_doc->headerdata['pos'] = $this->pos->pos_id;
        $this->_doc->headerdata['pos_name'] = $this->pos->pos_name;
        $this->_doc->headerdata['store'] = $this->_store_id;
        $this->_doc->headerdata['pricetype'] = $this->_pt;

        $this->_doc->firm_id = $this->pos->firm_id;

        $firm = H::getFirmData($this->_doc->firm_id);
        $this->_doc->headerdata["firm_name"] = $firm['firm_name'];
        $this->_doc->headerdata["inn"] = $firm['inn'];
        $this->_doc->headerdata["address"] = $firm['address'];
        $this->_doc->headerdata["phone"] = $firm['phone'];


        $this->_doc->packDetails('detaildata', $this->_itemlist);
        $this->_doc->packDetails('services', $this->_serlist);

        $this->_doc->amount = $this->form3->total2->getText();
        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {

            // проверка на минус  в  количестве
            $allowminus = System::getOption("common", "allowminus");
            if ($allowminus != 1) {

                foreach ($this->_itemlist as $item) {
                    $qty = $item->getQuantity($this->_doc->headerdata['store']);
                    if ($qty < $item->quantity) {
                        $this->setError("nominus", H::fqty($qty), $item->item_name);
                        return;
                    }
                }
            }

            if ($this->pos->usefisc == 1) {


                $ret = \App\Modules\PPO\PPOHelper::check($this->_doc);
                if ($ret['success'] == false && $ret['docnumber'] > 0) {
                    //повторяем для  нового номера
                    $this->pos->fiscdocnumber = $ret['docnumber'];
                    $this->pos->save();
                    $ret = \App\Modules\PPO\PPOHelper::check($this->_doc);

                }
                if ($ret['success'] == false) {
                    $this->setError($ret['data']);
                    return;
                } else {
                    //  $this->setSuccess("Выполнено") ;
                    if ($ret['docnumber'] > 0) {
                        $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                        $this->pos->save();
                        $this->_doc->headerdata["fiscalnumber"] = $ret['docnumber'];
                    } else {
                        $this->setError("ppo_noretnumber");
                        return;

                    }

                }

            }


            $this->_doc->save();
            $this->_doc->updateStatus(Document::STATE_NEW);

            $this->_doc->updateStatus(Document::STATE_EXECUTED);
            $conn->CommitTrans();
        } catch(\Exception $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());

            $logger->error($ee->getMessage() . " Документ " . $this->_doc->meta_desc);
            return;
        }
        $this->form3->customer->setKey(0);
        $this->form3->customer->setText('');
        $this->form3->payment->setValue(H::getDefMF());
        $this->form3->setVisible(false);
        $this->form4->setVisible(true);


        $check = $this->_doc->generatePosReport();
        $this->form4->showcheck->setText($check, true);
    }

    public function OnPayment($sender) {
        $b = $sender->getValue();
        $this->form3->payed->setVisible(true);
        $this->form3->payamount->setVisible(true);
        $this->form3->paydisc->setVisible(true);
        $this->form3->exchange->setVisible(true);

        if ($b == \App\Entity\MoneyFund::PREPAID) {
            $this->form3->payed->setVisible(false);
            $this->form3->payamount->setVisible(false);
            $this->form3->paydisc->setVisible(false);
            $this->form3->exchange->setVisible(false);
        }
        if ($b == \App\Entity\MoneyFund::CREDIT) {
            $this->form3->payed->setVisible(false);
            //$this->form3->payamount->setVisible(false);
            $this->form3->paydisc->setVisible(false);
            $this->form3->exchange->setVisible(false);
        }
    }


    public function OnOpenShift() {
        $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, true);
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, true);


        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {
            $this->setSuccess("ppo_shiftopened");
            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
                $this->_doc->headerdata["fiscalnumber"] = $ret['docnumber'];

            } else {
                $this->setError("ppo_noretnumber");
                return;

            }
            \App\Modules\PPO\PPOHelper::clearStat($this->pos->pos_id);

        }


        $this->pos->save();
        return true;
    }


    public function OnCloseShift($sender) {
        $ret = $this->zform();
        if ($ret == true) {
            $this->closeshift();
        }
    }


    public function zform() {

        $stat = \App\Modules\PPO\PPOHelper::getStat($this->pos->pos_id);
        $rstat = \App\Modules\PPO\PPOHelper::getStat($this->pos->pos_id, true);

        $ret = \App\Modules\PPO\PPOHelper::zform($this->pos->pos_id, $stat, $rstat);
        if (strpos($ret['data'], 'ZRepAlreadyRegistered')) {
            return true;
        }
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::zform($this->pos->pos_id, $stat, $rstat);


        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {

            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
            } else {
                $this->setError("ppo_noretnumber");
                return;

            }

        }


        return true;
    }


    public function closeshift() {
        $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, false);
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, false);


        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {
            $this->setSuccess("ppo_shiftclosed");
            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
            } else {
                $this->setError("ppo_noretnumber");
                return;

            }
            \App\Modules\PPO\PPOHelper::clearStat($this->pos->pos_id);
        }


        return true;
    }

}
