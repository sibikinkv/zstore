<?php

namespace App\Pages\Doc;

use App\Application as App;
use App\Entity\Customer;
use App\Entity\MoneyFund;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Store;
use App\Helper as H;
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
 * Страница  ввода  заказа
 */
class Order extends \App\Pages\Base
{

    public  $_tovarlist = array();
    private $_doc;
    private $_basedocid = 0;
    private $_rowid     = 0;


    public function __construct($docid = 0, $basedocid = 0) {
        parent::__construct();

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));

        $this->docform->add(new Date('document_date'))->setDate(time());

        $this->docform->add(new DropDownChoice('store', Store::getList(), H::getDefStore()));

        $this->docform->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');
        $this->docform->customer->onChange($this, 'OnChangeCustomer');

        $this->docform->add(new TextArea('notes'));
        $this->docform->add(new DropDownChoice('payment', MoneyFund::getList(true, false), H::getDefMF()))->onChange($this, 'OnPayment');


        $this->docform->add(new TextInput('editpaydisc'));
        $this->docform->add(new SubmitButton('bpaydisc'))->onClick($this, 'onPayDisc');
        $this->docform->add(new Label('paydisc', 0));


        $this->docform->add(new TextInput('editpayamount'));
        $this->docform->add(new SubmitButton('bpayamount'))->onClick($this, 'onPayAmount');
        $this->docform->add(new TextInput('editpayed', "0"));
        $this->docform->add(new SubmitButton('bpayed'))->onClick($this, 'onPayed');
        $this->docform->add(new Label('payed', 0));
        $this->docform->add(new Label('payamount', 0));


        $this->docform->add(new Label('discount'))->setVisible(false);
        $this->docform->add(new DropDownChoice('pricetype', Item::getPriceTypeList()))->onChange($this, 'OnChangePriceType');

        $this->docform->add(new DropDownChoice('delivery', Document::getDeliveryTypes()))->onChange($this, 'OnDelivery');
        $this->docform->add(new TextInput('email'));
        $this->docform->add(new TextInput('phone'));
        $this->docform->add(new TextArea('address'))->setVisible(false);


        $this->docform->add(new SubmitLink('addcust'))->onClick($this, 'addcustOnClick');

        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');


        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');

        $this->docform->add(new Label('total'));

        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));

        $this->editdetail->add(new AutocompleteTextInput('edittovar'))->onText($this, 'OnAutoItem');
        $this->editdetail->edittovar->onChange($this, 'OnChangeItem', true);

        $this->editdetail->add(new Label('qtystock'));

        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');

        //добавление нового кантрагента
        $this->add(new Form('editcust'))->setVisible(false);
        $this->editcust->add(new TextInput('editcustname'));
        $this->editcust->add(new TextInput('editcustphone'));
        $this->editcust->add(new Button('cancelcust'))->onClick($this, 'cancelcustOnClick');
        $this->editcust->add(new SubmitButton('savecust'))->onClick($this, 'savecustOnClick');

        if ($docid > 0) {    //загружаем   содержимок  документа настраницу
            $this->_doc = Document::load($docid)->cast();
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->document_date->setDate($this->_doc->document_date);
            $this->docform->pricetype->setValue($this->_doc->headerdata['pricetype']);

            $this->docform->delivery->setValue($this->_doc->headerdata['delivery']);
            $this->OnDelivery($this->docform->delivery);
            $this->docform->store->setValue($this->_doc->headerdata['store']);
            $this->docform->payment->setValue($this->_doc->headerdata['payment']);

            $this->docform->payamount->setText($this->_doc->payamount);
            $this->docform->editpayamount->setText($this->_doc->payamount);
            $this->docform->paydisc->setText($this->_doc->headerdata['paydisc']);
            $this->docform->editpaydisc->setText($this->_doc->headerdata['paydisc']);
            $this->docform->payed->setText($this->_doc->payed);
            $this->docform->editpayed->setText($this->_doc->payed);


            $this->docform->notes->setText($this->_doc->notes);
            $this->docform->email->setText($this->_doc->headerdata['email']);
            $this->docform->phone->setText($this->_doc->headerdata['phone']);
            $this->docform->address->setText($this->_doc->headerdata['ship_address']);
            $this->docform->customer->setKey($this->_doc->customer_id);
            $this->docform->customer->setText($this->_doc->customer_name);

            $this->_tovarlist = $this->_doc->unpackDetails('detaildata');
        } else {
            $this->_doc = Document::create('Order');
            $this->docform->document_number->setText($this->_doc->nextNumber());
            $this->_doc->headerdata['paydisc'] = 0;
            if ($basedocid > 0) {  //создание на  основании
                $basedoc = Document::load($basedocid);
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                }
            }
        }
        $this->OnPayment($this->docform->payment);
        
        $this->calcTotal();
        $this->calcPay();
        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_tovarlist')), $this, 'detailOnRow'))->Reload();
        if (false == \App\ACL::checkShowDoc($this->_doc)) {
            return;
        }
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('tovar', $item->itemname));

        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('msr', $item->msr));

        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));

        $row->add(new Label('amount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
    }

    public function deleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }
        $tovar = $sender->owner->getDataItem();
        // unset($this->_tovarlist[$tovar->tovar_id]);

        $this->_tovarlist = array_diff_key($this->_tovarlist, array($tovar->item_id => $this->_tovarlist[$tovar->item_id]));
        $this->docform->detail->Reload();
        $this->calcTotal();
        $this->calcPay();
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->editdetail->editquantity->setText("1");
        $this->editdetail->editprice->setText("0");
        $this->docform->setVisible(false);
        $this->_rowid = 0;
    }

    public function editOnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);

        $this->editdetail->editquantity->setText($item->quantity);
        $this->editdetail->editprice->setText($item->price);


        $this->editdetail->edittovar->setKey($item->item_id);
        $this->editdetail->edittovar->setText($item->itemname);

        $this->_rowid = $item->item_id;
    }

    public function saverowOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }
        $id = $this->editdetail->edittovar->getKey();
        if ($id == 0) {
            $this->setError("noselitem");
            return;
        }

        $item = Item::load($id);
        $item->quantity = $this->editdetail->editquantity->getText();


        $item->price = $this->editdetail->editprice->getText();


        unset($this->_tovarlist[$this->_rowid]);
        $this->_tovarlist[$item->item_id] = $item;
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();
        $this->calcTotal();
        $this->calcPay();
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
    }

    public function savedocOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }
        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = strtotime($this->docform->document_date->getText());
        $this->_doc->notes = $this->docform->notes->getText();
        $this->_doc->customer_id = $this->docform->customer->getKey();
        if ($this->_doc->customer_id > 0) {
            $customer = Customer::load($this->_doc->customer_id);
            $this->_doc->headerdata['customer_name'] = $this->docform->customer->getText() . ' ' . $customer->phone;
        }
        if ($this->checkForm() == false) {
            return;
        }

        $this->calcTotal();


        $this->_doc->headerdata['delivery'] = $this->docform->delivery->getValue();
        $this->_doc->headerdata['delivery_name'] = $this->docform->delivery->getValueName();
        $this->_doc->headerdata['ship_address'] = $this->docform->address->getText();
        $this->_doc->headerdata['phone'] = $this->docform->phone->getText();
        $this->_doc->headerdata['email'] = $this->docform->email->getText();
        $this->_doc->headerdata['pricetype'] = $this->docform->pricetype->getValue();
        $this->_doc->headerdata['store'] = $this->docform->store->getValue();

        $this->_doc->packDetails('detaildata', $this->_tovarlist);

        $this->_doc->amount = $this->docform->total->getText();

        $this->_doc->payamount = $this->docform->payamount->getText();

        $this->_doc->payed = $this->docform->payed->getText();
        $this->_doc->headerdata['paydisc'] = $this->docform->paydisc->getText();

        $this->_doc->headerdata['payment'] = $this->docform->payment->getValue();

        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID) {
            $this->_doc->headerdata['paydisc'] = 0;
            $this->_doc->payed = 0;
            $this->_doc->payamount = 0;
        }
        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::CREDIT) {
            $this->_doc->payed = 0;
        }


        $isEdited = $this->_doc->document_id > 0;

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            if ($this->_basedocid > 0) {
                $this->_doc->parent_id = $this->_basedocid;
                $this->_basedocid = 0;
            }
            $this->_doc->save();


            if ($sender->id == 'savedoc') {
                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);
            }


            if ($sender->id == 'execdoc') {
                $this->_doc->updateStatus(Document::STATE_INPROCESS);
            }


            $conn->CommitTrans();
            if ($sender->id == 'execdoc') {
                // App::Redirect("\\App\\Pages\\Doc\\TTN", 0, $this->_doc->document_id);

            }
            App::Redirect("\\App\\Pages\\Register\\OrderList");

        } catch(\Exception $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());

            $logger->error($ee->getMessage() . " Документ " . $this->_doc->meta_desc);
            return;
        }
    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal() {

        $total = 0;

        foreach ($this->_tovarlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;
        }
        $this->docform->total->setText(H::fa($total));


        $customer_id = $this->docform->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);

            if ($customer->discount > 0) {
                $disc = round($total * ($customer->discount / 100));
            } else {
                if ($customer->bonus > 0) {
                    if ($total >= $customer->bonus) {
                        $disc = $customer->bonus;
                    } else {
                        $disc = $total;
                    }
                }
            }
        }


        $this->docform->paydisc->setText($disc);
        $this->docform->editpaydisc->setText($disc);
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm() {
        if (strlen($this->_doc->document_number) == 0) {
            $this->setError('enterdocnumber');
        }
        if (false == $this->_doc->checkUniqueNumber()) {
            $this->docform->document_number->setText($this->_doc->nextNumber());
            $this->setError('nouniquedocnumber_created');
        }
        if (count($this->_tovarlist) == 0) {
            $this->setError("noenteritem");
        }

        $c = $this->docform->customer->getKey();
        if ($c == 0) {
            $this->setError("noselcust");
        }
        return !$this->isError();
    }

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $item = Item::load($id);
        $price = $item->getPrice($this->docform->pricetype->getValue());


        $this->editdetail->qtystock->setText(H::fqty($item->getQuantity($this->docform->store->getValue())));
        $this->editdetail->editprice->setText($price);

        $this->updateAjax(array('qtystock', 'editprice'));
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    public function OnChangeCustomer($sender) {

        $customer_id = $this->docform->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);

            $this->docform->phone->setText($customer->phone);
            $this->docform->email->setText($customer->email);
            $this->docform->address->setText($customer->address);


            if ($customer->discount > 0) {
                $this->docform->discount->setText("Постоянная скидка " . $customer->discount . '%');
                $this->docform->discount->setVisible(true);
            } else {
                if ($customer->bonus > 0) {
                    $this->docform->discount->setText("Бонусы " . $customer->bonus);
                    $this->docform->discount->setVisible(true);
                }
            }
        }


        $this->calcTotal();

        $this->calcPay();
    }

    public function OnAutoItem($sender) {
        $text = Item::qstr('%' . $sender->getText() . '%');
        return Item::findArray("itemname", "  (itemname like {$text} or item_code like {$text})  and disabled <> 1 ");
    }

    //добавление нового контрагента
    public function addcustOnClick($sender) {
        $this->editcust->setVisible(true);
        $this->docform->setVisible(false);

        $this->editcust->editcustname->setText('');
        $this->editcust->editcustphone->setText('');
    }

    public function savecustOnClick($sender) {
        $custname = trim($this->editcust->editcustname->getText());
        if (strlen($custname) == 0) {
            $this->setError("entername");
            return;
        }
        $cust = new Customer();
        $cust->customer_name = $custname;
        $cust->phone = $this->editcust->editcustphone->getText();

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
        $this->docform->customer->setText($cust->customer_name);
        $this->docform->customer->setKey($cust->customer_id);

        $this->editcust->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->discount->setVisible(false);

        $this->docform->phone->setText($cust->phone);
    }

    public function cancelcustOnClick($sender) {
        $this->editcust->setVisible(false);
        $this->docform->setVisible(true);
    }

    public function OnDelivery($sender) {

        if ($sender->getValue() == Document::DEL_BOY || $sender->getValue() == Document::DEL_SERVICE) {
            $this->docform->address->setVisible(true);
        } else {
            $this->docform->address->setVisible(false);
        }
    }

    public function OnChangePriceType($sender) {
        foreach ($this->_tovarlist as $item) {
            //$item = Item::load($item->item_id);
            $price = $item->getPrice($this->docform->pricetype->getValue());
            $item->price = $price;
        }
        $this->calcTotal();
        $this->docform->detail->Reload();
        $this->calcTotal();
    }


    public function onPayAmount($sender) {
        $this->docform->payamount->setText($this->docform->editpayamount->getText());
        $this->goAnkor("tankor");
    }


    public function onPayed($sender) {
        $this->docform->payed->setText(H::fa($this->docform->editpayed->getText()));
        $payed = $this->docform->payed->getText();
        $payamount = $this->docform->payamount->getText();
        if ($payed > $payamount) {

            $this->setWarn('inserted_extrasum');
        } else {
            $this->goAnkor("tankor");
        }
        $this->calcPay();
    }

    public function onPayDisc() {
        $this->docform->paydisc->setText($this->docform->editpaydisc->getText());
        $this->calcPay();
        $this->goAnkor("tankor");
    }

    private function calcPay() {
        $total = $this->docform->total->getText();
        $disc = $this->docform->paydisc->getText();

        if ($disc > 0) {
            $total -= $disc;
        }
        
        $p = $this->docform->payment->getValue() ;
        
        if($p  >0) {
          $this->docform->editpayamount->setText(H::fa($total));
          $this->docform->payamount->setText(H::fa($total));
        
        }
        if($p  >0 &&  $p < 10000) {
            $this->docform->editpayed->setText(H::fa($total));
            $this->docform->payed->setText(H::fa($total));
        
        }
        
    }
   
    public function OnPayment($sender) {
        $this->docform->payed->setVisible(true);
        $this->docform->payamount->setVisible(true);
        $this->docform->paydisc->setVisible(true);

        $b = $sender->getValue();


        if ($b == 0) {
            $this->docform->payed->setVisible(false);
            $this->docform->payamount->setVisible(false);
            $this->docform->paydisc->setVisible(false);
            $this->docform->payed->setText(0);
            $this->docform->editpayed->setText(0);
            $this->docform->payamount->setText(0);
            $this->docform->editpayamount->setText(0);
            $this->docform->paydisc->setText(0);
            $this->docform->editpaydisc->setText(0);
            
        }
        if ($b == \App\Entity\MoneyFund::CREDIT) {
            $this->docform->payed->setVisible(false);
            $this->docform->payed->setText(0);
            $this->docform->editpayed->setText(0);
        }
        $this->calcPay() ;
        
    }

}
