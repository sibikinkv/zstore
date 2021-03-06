<?php

namespace App\Pages\Register;

use App\Application as App;
use App\Entity\Doc\Document;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\DataList\Paginator;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Panel;
use App\Entity\Pay;

/**
 * журнал  заказов
 */
class OrderList extends \App\Pages\Base
{

    private $_doc = null;

    /**
     *
     * @param mixed $docid Документ  должен  быть  показан  в  просмотре
     * @return DocList
     */
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReg('OrderList')) {
            return;
        }

        $this->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');

        $this->filter->add(new TextInput('searchnumber'));
        $this->filter->add(new TextInput('searchtext'));
        $this->filter->add(new DropDownChoice('status', array(0 => 'Открытые', 1 => 'Новые', 2 => 'Неоплаченые', 3 => 'Все'), 0));

        $doclist = $this->add(new DataView('doclist', new OrderDataSource($this), $this, 'doclistOnRow'));
        $doclist->setSelectedClass('table-success');

        $this->add(new Paginator('pag', $doclist));
        $doclist->setPageSize(H::getPG());

        $this->add(new Panel("statuspan"))->setVisible(false);

        $this->statuspan->add(new Form('statusform'));

        $this->statuspan->statusform->add(new SubmitButton('bclose'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('binp'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('binv'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bref'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bttn'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('btask'))->onClick($this, 'statusOnSubmit');

        $this->statuspan->add(new \App\Widgets\DocView('docview'));

        $this->doclist->Reload();
        $this->add(new ClickLink('csv', $this, 'oncsv'));

        $this->add(new Form('payform'))->onSubmit($this, 'payOnSubmit');
        $this->payform->add(new DropDownChoice('payment', \App\Entity\MoneyFund::getList(), H::getDefMF()));
        $this->payform->add(new DropDownChoice('pos', \App\Entity\Pos::findArray('pos_name', "details like '%<usefisc>1</usefisc>%' "), 0));
        $this->payform->add(new TextInput('pamount'));
        $this->payform->add(new TextInput('pcomment'));
        $this->payform->add(new CheckBox('closeorder'));
        $this->payform->add(new Date('pdate', time()));
        $this->payform->setVisible(false);


    }

    public function filterOnSubmit($sender) {

        $this->statuspan->setVisible(false);
        $this->payform->setVisible(false);

        $this->doclist->Reload();
    }

    public function doclistOnRow($row) {
        $doc = $row->getDataItem();

        $row->add(new Label('number', $doc->document_number));

        $row->add(new Label('date', H::fd($doc->document_date)));
        $row->add(new Label('onotes', $doc->notes));
        $row->add(new Label('customer', $doc->customer_name));
        $row->add(new Label('amount', H::fa($doc->amount)));

        $row->add(new Label('state', Document::getStateName($doc->state)));

        $row->add(new ClickLink('show'))->onClick($this, 'showOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->add(new ClickLink('pay', $this, 'payOnClick'))->setVisible($doc->payamount > 0 && $doc->payamount > $doc->payed);

        if ($doc->state < Document::STATE_EXECUTED) {
            $row->edit->setVisible(true);
        } else {
            $row->edit->setVisible(false);
        }
    }

    public function statusOnSubmit($sender) {
        if (\App\Acl::checkChangeStateDoc($this->_doc, true, true) == false) {
            return;
        }

        $state = $this->_doc->state;


        //проверяем  что есть ТТН
        $list = $this->_doc->getChildren('GoodsIssue');
        $ttn = count($list) > 0;
        $list = $this->_doc->getChildren('Invoice');
        $invoice = count($list) > 0;


        if ($sender->id == "binp") {
            $this->_doc->updateStatus(Document::STATE_INPROCESS);
        }
        if ($sender->id == "bref") {
            $this->_doc->updateStatus(Document::STATE_REFUSED);

            $this->setWarn('order_canceled');
        }
        if ($sender->id == "btask") {
            $task = count($this->_doc->getChildren('Task')) > 0;

            if ($task) {

                $this->setWarn('task_exists');
            }
            App::Redirect("\\App\\Pages\\Doc\\Task", 0, $this->_doc->document_id);
        }
        if ($sender->id == "bttn") {
            if ($ttn) {
                $this->setWarn('order_has_sent');
            }
            App::Redirect("\\App\\Pages\\Doc\\TTN", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "binv") {
            if ($invoice) {
                $this->setWarn('invoice_exists');
            }
            App::Redirect("\\App\\Pages\\Doc\\Invoice", 0, $this->_doc->document_id);
            return;
        }


        if ($sender->id == "bclose") {

            //  $this->_doc->payamount = $this->_doc->amount;
            // $this->_doc->save();

            $this->_doc->updateStatus(Document::STATE_CLOSED);
            $this->statuspan->setVisible(false);
        }

        $this->doclist->Reload(false);
        $this->updateStatusButtons();
    }

    public function updateStatusButtons() {

        $this->statuspan->statusform->bclose->setVisible(true);

        $state = $this->_doc->state;

        //доставлен
        $sent = $this->_doc->checkStates(array(Document::STATE_DELIVERED));
        //выполняется
        $inproc = $this->_doc->checkStates(array(Document::STATE_INPROCESS));
        //аннулирован
        $ref = $this->_doc->checkStates(array(Document::STATE_REFUSED));


        //проверяем  что есть ТТН
        $list = $this->_doc->getChildren('TTN');
        $ttn = count($list) > 0;
        $list = $this->_doc->getChildren('Invoice');
        $invoice = count($list) > 0;

        $this->statuspan->statusform->bttn->setVisible(!$sent);
        $this->statuspan->statusform->binv->setVisible(!$sent);

        //новый
        if ($state < Document::STATE_EXECUTED) {
            $this->statuspan->statusform->btask->setVisible(false);

            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->binv->setVisible(false);
            $this->statuspan->statusform->binp->setVisible(true);

        } else {

            $this->statuspan->statusform->bclose->setVisible(true);
            $this->statuspan->statusform->bref->setVisible(true);
            $this->statuspan->statusform->binp->setVisible(false);
            $this->statuspan->statusform->btask->setVisible(true);

        }

        if ($ttn) {
            $this->statuspan->statusform->bref->setVisible(false);
        }
        if ($ref) {
            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->binv->setVisible(false);
        }


        //закрыт
        if ($state == Document::STATE_CLOSED) {

            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->btask->setVisible(false);
            $this->statuspan->statusform->binv->setVisible(false);
            $this->statuspan->statusform->binp->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->setVisible(false);
        }

        if ($this->_doc->payamount > 0 && $this->_doc->payamount > $this->_doc->payed) {
            $this->statuspan->statusform->bclose->setVisible(false);
        }


        $this->_tvars['askclose'] = false;
        if ($inproc == false || $sent == false) {
            $this->_tvars['askclose'] = true;
        }
    }

    //просмотр
    public function showOnClick($sender) {
        $this->payform->setVisible(false);

        $this->_doc = $sender->owner->getDataItem();
        if (false == \App\ACL::checkShowDoc($this->_doc, true)) {
            return;
        }

        $this->statuspan->setVisible(true);
        $this->statuspan->statusform->setVisible(true);
        $this->statuspan->statusform->setVisible(true);
        $this->statuspan->docview->setDoc($this->_doc);
        $this->doclist->setSelectedRow($sender->getOwner());
        $this->doclist->Reload(true);
        $this->updateStatusButtons();
        $this->goAnkor('dankor');
        $this->_tvars['askclose'] = false;
    }

    public function editOnClick($sender) {
        $doc = $sender->getOwner()->getDataItem();
        if (false == \App\ACL::checkEditDoc($doc, true)) {
            return;
        }

        App::Redirect("\\App\\Pages\\Doc\\Order", $doc->document_id);
    }

    public function oncsv($sender) {
        $list = $this->doclist->getDataSource()->getItems(-1, -1, 'document_id');

        $header = array();
        $data = array();

        $i = 0;
        foreach ($list as $d) {
            $i++;
            $data['A' . $i] = H::fd($d->document_date);
            $data['B' . $i] = $d->document_number;
            $data['C' . $i] = $d->customer_name;
            $data['D' . $i] = $d->amount;
            $data['E' . $i] = Document::getStateName($d->state);
            $data['F' . $i] = $d->notes;

        }

        H::exportExcel($data, $header, 'orderlist.xlsx');


    }

    public function payOnClick($sender) {
        $this->statuspan->setVisible(false);
        $this->payform->setVisible(true);
        $this->doclist->setSelectedRow($sender->getOwner());
        $this->doclist->Reload(true);

        $this->_doc = $sender->owner->getDataItem();


        $this->goAnkor('dankor');

        $this->payform->pamount->setText($this->_doc->payamount - $this->_doc->payed);;
        $this->payform->pcomment->setText("");;

        $this->payform->pos->setVisible(false);//пока  без  фискализации

        $this->payform->closeorder->setVisible(false);

        $delivered = 0;
        $list = $this->_doc->getChildren('TTN');
        foreach ($list as $ttn) {
            if ($ttn->state == Document::STATE_DELIVERED) {
                $delivered++;
            }
        }
        if ($delivered > 0 && $delivered == count($list)) {
            $this->payform->closeorder->setVisible(true);

        }

        $this->payform->closeorder->setChecked(false);
    }

    public function payOnSubmit($sender) {
        $form = $this->payform;
        $pos_id = $form->pos->getValue();
        $amount = $form->pamount->getText();
        $pdate = $form->pdate->getDate();
        if ($amount == 0) {
            return;
        }


        if ($amount > $this->_doc->payamount - $this->_doc->payed) {

            $this->setWarn('sumoverpay');

        }


        if ($pos_id > 0) {
            $pos = \App\Entity\Pos::load($pos_id);

            $ret = \App\Modules\PPO\PPOHelper::checkpay($this->_doc, $pos_id, $amount, $form->payment->getValue());
            if ($ret['success'] == false && $ret['docnumber'] > 0) {
                //повторяем для  нового номера
                $pos->fiscdocnumber = $ret['docnumber'];
                $pos->save();
                $ret = \App\Modules\PPO\PPOHelper::check($this->_doc);

            }
            if ($ret['success'] == false) {
                $this->setError($ret['data']);
                return;
            } else {

                if ($ret['docnumber'] > 0) {
                    $pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                    $pos->save();
                    $this->_doc->headerdata["fiscalnumber"] = $ret['docnumber'];
                } else {
                    $this->setError("ppo_noretnumber");
                    return;

                }

            }

        }

        Pay::addPayment($this->_doc->document_id, $pdate, $amount, $form->payment->getValue(), Pay::PAY_BASE_INCOME, $form->pcomment->getText());


        $this->setSuccess('payment_added');


        if ($this->payform->closeorder->isChecked() == true) {
            $doc = Document::load($this->_doc->document_id);     //загружаем  тобы  обновить  оплату
            $doc->updateStatus(Document::STATE_CLOSED);
        }


        $this->doclist->Reload(false);
        $this->payform->setVisible(false);


    }

}

/**
 *  Источник  данных  для   списка  документов
 */
class OrderDataSource implements \Zippy\Interfaces\DataSource
{

    private $page;

    public function __construct($page) {
        $this->page = $page;
    }

    private function getWhere() {
        $user = System::getUser();

        $conn = \ZDB\DB::getConnect();


        $where = "     meta_name  = 'Order'  ";

        $status = $this->page->filter->status->getValue();
        if ($status == 0) {
            $where .= " and  state <> 9 ";
        }
        if ($status == 1) {
            $where .= " and  state =1 ";
        }


        $st = trim($this->page->filter->searchtext->getText());
        if (strlen($st) > 2) {
            $st = $conn->qstr('%' . $st . '%');

            $where .= " and  meta_name  = 'Order'  and  content like {$st} ";
        }
        $sn = trim($this->page->filter->searchnumber->getText());
        if (strlen($sn) > 1) { // игнорируем другие поля
            $sn = $conn->qstr('%' . $sn . '%');
            $where = "  meta_name  = 'Order'   document_number like  {$sn} ";
        }

        return $where;
    }

    public function getItemCount() {
        return Document::findCnt($this->getWhere());
    }

    public function getItems($start, $count, $sortfield = null, $asc = null) {
        $docs = Document::find($this->getWhere(), "document_date desc,document_id desc", $count, $start);

        return $docs;
    }

    public function getItem($id) {

    }

}
