<?php

namespace App\Pages\Service;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Item;
use App\Entity\Store;
use App\Helper as H;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\TextInput;
use Zippy\WebApplication as App;

class Import extends \App\Pages\Base
{

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('Import')) {
            return;
        }

        //ТМЦ
        $form = $this->add(new Form("iform"));

        $form->add(new DropDownChoice("itype", array(), 0))->onChange($this, "onType");

        $form->add(new DropDownChoice("price", Item::getPriceTypeList()));
        $form->add(new DropDownChoice("store", Store::getList(), H::getDefStore()));

        $form->add(new \Zippy\Html\Form\File("filename"));
        $cols = array(0 => '-', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H', 'I' => 'I', 'J' => 'J');
        $form->add(new DropDownChoice("colname", $cols));
        $form->add(new DropDownChoice("colcode", $cols));
        $form->add(new DropDownChoice("colbarcode", $cols));
        $form->add(new DropDownChoice("colgr", $cols));
        $form->add(new DropDownChoice("colqty", $cols));
        $form->add(new DropDownChoice("colprice", $cols));
        $form->add(new DropDownChoice("colinprice", $cols));
        $form->add(new DropDownChoice("colmsr", $cols));
        $form->add(new DropDownChoice("colbrand", $cols));
        $form->add(new DropDownChoice("coldesc", $cols));
        $form->add(new CheckBox("passfirst"));
        $form->add(new CheckBox("preview"));
        $form->add(new CheckBox("checkname"));

        $form->onSubmit($this, "onImport");

        $this->onType($form->itype);


        //накладная
        $form = $this->add(new Form("nform"));


        $form->add(new DropDownChoice("nstore", Store::getList(), H::getDefStore()));

        $form->add(new AutocompleteTextInput("ncust"))->onText($this, 'OnAutoCustomer');
        $form->add(new \Zippy\Html\Form\File("nfilename"));

        $form->add(new DropDownChoice("ncolname", $cols));
        $form->add(new DropDownChoice("ncolcode", $cols));
        $form->add(new DropDownChoice("ncolqty", $cols));
        $form->add(new DropDownChoice("ncolprice", $cols));
        $form->add(new DropDownChoice("ncolmsr", $cols));
        $form->add(new CheckBox("npassfirst"));
        $form->add(new CheckBox("npreview"));

        $form->onSubmit($this, "onNImport");

        //контрагенты
        $form = $this->add(new Form("cform"));

        $form->add(new DropDownChoice("ctype", array(), 0));


        $form->add(new CheckBox("cpreview"));
        $form->add(new CheckBox("cpassfirst"));
        $form->add(new DropDownChoice("colcname", $cols));
        $form->add(new DropDownChoice("colphone", $cols));
        $form->add(new DropDownChoice("colemail", $cols));
        $form->add(new DropDownChoice("colcity", $cols));
        $form->add(new DropDownChoice("coladdress", $cols));
        $form->add(new \Zippy\Html\Form\File("cfilename"));

        $form->onSubmit($this, "onCImport");


        $this->_tvars['preview'] = false;
        $this->_tvars['preview2'] = false;
        $this->_tvars['preview3'] = false;
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList($sender->getText());
    }


    public function onType($sender) {
        $t = $sender->getValue();

        $this->iform->colqty->setVisible($t == 1);
        $this->iform->store->setVisible($t == 1);
        $this->iform->colinprice->setVisible($t == 1);
    }

    public function onImport($sender) {
        $t = $this->iform->itype->getValue();
        $store = $this->iform->store->getValue();
        $pt = $this->iform->price->getValue();

        $preview = $this->iform->preview->isChecked();
        $preview = $this->iform->preview->isChecked();
        $checkname = $this->iform->checkname->isChecked();
        $this->_tvars['preview'] = false;

        $colname = $this->iform->colname->getValue();
        $colcode = $this->iform->colcode->getValue();
        $colbarcode = $this->iform->colbarcode->getValue();
        $colgr = $this->iform->colgr->getValue();
        $colqty = $this->iform->colqty->getValue();
        $colprice = $this->iform->colprice->getValue();
        $colinprice = $this->iform->colinprice->getValue();
        $colmsr = $this->iform->colmsr->getValue();
        $colbrand = $this->iform->colbrand->getValue();
        $coldesc = $this->iform->coldesc->getValue();
        if ($colname === '0') {
            $this->setError('noselcolname');
            return;
        }
        if ($t == 1 && $colqty === '0') {
            $this->setError('noselcolqty');
            return;
        }
        $file = $this->iform->filename->getFile();
        if (strlen($file['tmp_name']) == 0) {

            $this->setError('noselfile');
            return;
        }

        $data = array();
        $oSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']); // Вариант и для xls и xlsX


        $oCells = $oSpreadsheet->getActiveSheet()->getCellCollection();

        for ($iRow = ($passfirst ? 2 : 1); $iRow <= $oCells->getHighestRow(); $iRow++) {

            $row = array();
            for ($iCol = 'A'; $iCol <= $oCells->getHighestColumn(); $iCol++) {
                $oCell = $oCells->get($iCol . $iRow);
                if ($oCell) {
                    $row[$iCol] = $oCell->getValue();
                }

            }
            $data[$iRow] = $row;


        }

        unset($oSpreadsheet);

        if ($preview) {

            $this->_tvars['preview'] = true;
            $this->_tvars['list'] = array();
            foreach ($data as $row) {

                $this->_tvars['list'][] = array(
                    'colname'    => $row[$colname],
                    'colcode'    => $row[$colcode],
                    'colbarcode' => $row[$colbarcode],
                    'colgr'      => $row[$colgr],
                    'colqty'     => $row[$colqty],
                    'colmsr'     => $row[$colmsr],
                    'colinprice' => $row[$colinprice],
                    'colprice'   => $row[$colprice],
                    'colbrand'   => $row[$colbrand],
                    'coldesc'    => $row[$coldesc]
                );
            }
            return;
        }

        $cnt = 0;
        $newitems = array();
        foreach ($data as $row) {

            $catname = $row[$colgr];
            if (strlen($catname) > 0) {
                $cat = Category::getFirst('cat_name=' . Category::qstr($catname));
                if ($cat == null) {
                    $cat = new Category();
                    $cat->cat_name = $catname;
                    $cat->save();
                }
            }
            $item = null;
            $itemname = trim($row[$colname]);
            $itemcode = trim($row[$colcode]);
            $itembarcode = trim($row[$colbarcode]);
            if (strlen($itemname) > 0) {

                if (strlen($itembarcode) > 0) {
                    $item = Item::getFirst('bar_code=' . Item::qstr($itembarcode));
                } else {
                    if (strlen($itemcode) > 0) {
                        $item = Item::getFirst('item_code=' . Item::qstr($itemcode));
                    }
                }
                if ($item == null && $checkname == true) {
                    $item = Item::getFirst('itemname=' . Item::qstr($itemname));
                }


                if ($item == null) {
                    $price = str_replace(',', '.', trim($row[$colprice]));
                    $inprice = str_replace(',', '.', trim($row[$colinprice]));
                    $qty = str_replace(',', '.', trim($row[$colqty]));
                    $item = new Item();
                    $item->itemname = $itemname;
                    if (strlen($row[$colcode]) > 0) {
                        $item->item_code = trim($row[$colcode]);
                    }
                    if (strlen($row[$colbarcode]) > 0) {
                        $item->bar_code = trim($row[$colbarcode]);
                    }
                    if (strlen($row[$colmsr]) > 0) {
                        $item->msr = trim($row[$colmsr]);
                    }
                    if (strlen($row[$colbrand]) > 0) {
                        $item->manufacturer = trim($row[$colbrand]);
                    }
                    if (strlen(trim($row[$coldesc])) > 0) {
                        $item->description = trim($row[$coldesc]);
                    }
                    if ($price > 0) {
                        $item->{$pt} = $price;
                    }
                    if ($inprice > 0) {
                        $item->price = $inprice;
                    }
                    if ($qty > 0) {
                        $item->quantity = $qty;
                    }
                    if ($cat->cat_id > 0) {
                        $item->cat_id = $cat->cat_id;
                    }

                    $item->amount = $item->quantity * $item->price;
                    $item->save();
                    $cnt++;
                    if ($item->quantity > 0) {
                        $newitems[] = $item; //для склада   
                    }
                }
            }
        }
        if (count($newitems) > 0) {
            $doc = \App\Entity\Doc\Document::create('IncomeItem');
            $doc->document_number = $doc->nextNumber();
            if (strlen($doc->document_number) == 0) {
                $doc->document_number = "ПТ00001";
            }
            $doc->document_date = time();

            $amount = 0;
            $itlist = array();
            foreach ($newitems as $item) {
                $itlist[] = $item;
                $amount = $amount + ($item->quantity * $item->price);
            }
            $doc->packDetails('detaildata', $itlist);
            $doc->amount = H::fa($amount);
            $doc->payamount = 0;
            $doc->payed = 0;
            $doc->notes = 'Импорт с Excel';
            $doc->headerdata['store'] = $store;

            $doc->save();
            $doc->updateStatus(\App\Entity\Doc\Document::STATE_NEW);
            $doc->updateStatus(\App\Entity\Doc\Document::STATE_EXECUTED);
        }

        $this->setSuccess("imported_items", $cnt);


    }


    public function onCImport($sender) {
        $t = $this->cform->ctype->getValue();


        $preview = $this->cform->cpreview->isChecked();
        $passfirst = $this->cform->cpassfirst->isChecked();
        $this->_tvars['preview2'] = false;

        $colcname = $this->cform->colcname->getValue();
        $colphone = $this->cform->colphone->getValue();
        $colemail = $this->cform->colemail->getValue();
        $colcity = $this->cform->colcity->getValue();
        $coladdress = $this->cform->coladdress->getValue();


        if ($colcname === '0') {
            $this->setError('noselcolname');
            return;
        }

        $file = $this->cform->cfilename->getFile();
        if (strlen($file['tmp_name']) == 0) {
            $this->setError('noselfile');
            return;
        }


        $data = array();

        $oSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']); // Вариант и для xls и xlsX


        $oCells = $oSpreadsheet->getActiveSheet()->getCellCollection();

        for ($iRow = ($passfirst ? 2 : 1); $iRow <= $oCells->getHighestRow(); $iRow++) {

            $row = array();
            for ($iCol = 'A'; $iCol <= $oCells->getHighestColumn(); $iCol++) {
                $oCell = $oCells->get($iCol . $iRow);
                if ($oCell) {
                    $row[$iCol] = $oCell->getValue();
                }

            }
            $data[$iRow] = $row;


        }

        unset($oSpreadsheet);


        if ($preview) {

            $this->_tvars['preview2'] = true;
            $this->_tvars['list2'] = array();
            foreach ($data as $row) {

                $this->_tvars['list2'][] = array(
                    'colname'    => $row[$colcname],
                    'colphone'   => $row[$colphone],
                    'colemail'   => $row[$colemail],
                    'colcity'    => $row[$colcity],
                    'coladdress' => $row[$coladdress]
                );
            }
            return;
        }

        $cnt = 0;
        $newitems = array();
        foreach ($data as $row) {

            $c = null;
            $name = $row[$colcname];
            $phone = $row[$colphone];

            if (strlen(trim($name)) == 0) {
                continue;
            }

            if (strlen(trim($phone)) > 0) {
                $c = Customer::getFirst('phone=' . Customer::qstr($phone));
            }

            if ($c == null) {

                $c = new Customer();
                $c->type = $t;
                $c->customer_name = $name;

                if (strlen($row[$colphone]) > 0) {
                    $c->phone = $row[$colphone];
                }
                if (strlen($row[$colemail]) > 0) {
                    $c->email = $row[$colemail];
                }
                if (strlen($row[$colcity]) > 0) {
                    $c->city = $row[$colcity];
                }
                if (strlen($row[$coladdress]) > 0) {
                    $c->address = $row[$coladdress];
                }


                $c->save();
                $cnt++;

            }

        }

        $this->setSuccess("imported_customers ", $cnt);


    }


    public function onNImport($sender) {
        $store = $this->nform->nstore->getValue();
        $c = $this->nform->ncust->getKey();

        $preview = $this->nform->npreview->isChecked();
        $passfirst = $this->nform->npassfirst->isChecked();
        $this->_tvars['preview3'] = false;


        $colname = $this->nform->ncolname->getValue();
        $colcode = $this->nform->ncolcode->getValue();
        $colqty = $this->nform->ncolqty->getValue();
        $colprice = $this->nform->ncolprice->getValue();
        $colmsr = $this->nform->ncolmsr->getValue();


        if ($colname === '0') {
            $this->setError('noselcolname');
            return;
        }
        if ($colqty === '0') {
            $this->setError('noselcolqty');
            return;
        }

        if ($c == 0) {
            $this->setError('noselsender');
            return;
        }

        $file = $this->nform->nfilename->getFile();
        if (strlen($file['tmp_name']) == 0) {

            $this->setError('noselfile');
            return;
        }

        $data = array();
        $oSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']); // Вариант и для xls и xlsX


        $oCells = $oSpreadsheet->getActiveSheet()->getCellCollection();

        for ($iRow = ($passfirst ? 2 : 1); $iRow <= $oCells->getHighestRow(); $iRow++) {

            $row = array();
            for ($iCol = 'A'; $iCol <= $oCells->getHighestColumn(); $iCol++) {
                $oCell = $oCells->get($iCol . $iRow);
                if ($oCell) {
                    $row[$iCol] = $oCell->getValue();
                }

            }
            $data[$iRow] = $row;


        }

        unset($oSpreadsheet);

        if ($preview) {

            $this->_tvars['preview3'] = true;
            $this->_tvars['list'] = array();
            foreach ($data as $row) {

                $this->_tvars['list'][] = array(
                    'colname'  => $row[$colname],
                    'colcode'  => $row[$colcode],
                    'colqty'   => $row[$colqty],
                    'colmsr'   => $row[$colmsr],
                    'colprice' => $row[$colprice]
                );
            }
            return;
        }

        $cnt = 0;
        $items = array();
        foreach ($data as $row) {


            $item = null;
            $itemname = trim($row[$colname]);
            $itemcode = trim($row[$colcode]);
            if (strlen($itemname) > 0) {

                if (strlen($itemcode) > 0) {
                    $item = Item::getFirst('item_code=' . Item::qstr($itemcode));
                }
                if ($item == null) {
                    $item = Item::getFirst('itemname=' . Item::qstr($itemname));
                }

                $price = str_replace(',', '.', trim($row[$colprice]));
                $qty = str_replace(',', '.', trim($row[$colqty]));

                if ($item == null) {
                    $item = new Item();
                    $item->itemname = $itemname;
                    if (strlen($row[$colcode]) > 0) {
                        $item->item_code = trim($row[$colcode]);
                    }
                    if (strlen($row[$colmsr - 1]) > 0) {
                        $item->msr = trim($row[$colmsr]);
                    }


                    $item->save();


                }
                if ($qty > 0) {
                    $item->price = $price;
                    $item->quantity = $qty;

                    $items[] = $item;
                }

            }
        }
        if (count($items) > 0) {
            $doc = \App\Entity\Doc\Document::create('GoodsReceipt');
            $doc->document_number = $doc->nextNumber();
            if (strlen($doc->document_number) == 0) {
                $doc->document_number = "ПН00001";
            }
            $doc->document_date = time();

            $amount = 0;
            $itlist = array();
            foreach ($items as $item) {
                $itlist[] = $item;
                $amount = $amount + ($item->quantity * $item->price);
            }
            $doc->packDetails('detaildata', $itlist);
            $doc->amount = H::fa($amount);
            $doc->payamount = 0;
            $doc->payed = 0;
            $doc->notes = 'Импорт с Excel';
            $doc->headerdata['store'] = $store;
            $doc->customer_id = $c;
            $doc->headerdata['customer_name'] = $this->nform->ncust->getText();

            $doc->save();
            $doc->updateStatus(\App\Entity\Doc\Document::STATE_NEW);
            App::Redirect("\\App\\Pages\\Doc\\GoodsReceipt", $doc->document_id);
        }


    }


}
