<?php

namespace App\Entity\Doc;

use \App\Entity\Entry;
use \App\Helper as H;
use \App\Util;

/**
 * Класс-сущность  документ расходная  накладная
 *
 */
class GoodsIssue extends Document {

    public function generateReport() {


        $i = 1;
        $detail = array();

        foreach ($this->detaildata as $value) {

            if (isset($detail[$value['item_id']])) {
                $detail[$value['item_id']]['quantity'] += $value['quantity'];
            } else {
                $name = $value['itemname'];
                if (strlen($value['snumber']) > 0) {
                    $name .= ' (' . $value['snumber'] . ',' . date('d.m.Y', $value['sdate']) . ')';
                }


                $detail[] = array("no" => $i++,
                    "tovar_name" => $name,
                    "tovar_code" => $value['item_code'],
                    "quantity" => H::fqty($value['quantity']),
                    "msr" => $value['msr'],
                    "price" => $value['price'],
                    "amount" => round($value['quantity'] * $value['price'])
                );
            }
        }


        $customer = \App\Entity\Customer::load($this->customer_id);

        $header = array('date' => date('d.m.Y', $this->document_date),
            "_detail" => $detail,
            "firmname" => $firm['firmname'],
            "customername" => $this->customer_name . ', тел. ' . $customer->phone,
            "ship_address" => $this->headerdata["ship_address"],
            "ship_number" => $this->headerdata["ship_number"],
            "order" => $this->headerdata["order"],
            "emp_name" => $this->headerdata["emp_name"],
            "document_number" => $this->document_number,
            "total" => $this->amount,
            "payed" => $this->headerdata['payed'],
            "paydisc" => $this->headerdata["paydisc"],
            "prepaid" => $this->headerdata['prepaid'] == 1,
            "payamount" => $this->payamount
        );
        if ($this->headerdata["sent_date"] > 0) {
            $header['sent_date'] = date('d.m.Y', $this->headerdata["sent_date"]);
        }
        if ($this->headerdata["delivery_date"] > 0) {
            $header['delivery_date'] = date('d.m.Y', $this->headerdata["delivery_date"]);
        }
        $header["isorder"] = $this->headerdata["delivery"] > 1;

        $report = new \App\Report('goodsissue.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        //$conn = \ZDB\DB::getConnect();


        foreach ($this->detaildata as $item) {
            $listst = \App\Entity\Stock::pickup($this->headerdata['store'], $item['item_id'], $item['quantity'], $item['snumber'] );
                 if (count($listst) == 0) {
                \App\System::setErrorMsg('Недостаточно товара ' . $item['itemname']);
                return false;
            }
            foreach ($listst as $st) {
                $sc = new Entry($this->document_id, 0 - $st->quantity * $st->partion, 0 - $st->quantity);
                $sc->setStock($st->stock_id);
                $sc->setExtCode($item['price'] - $st->partion); //Для АВС 
                $sc->save();
            }
        }

        //списываем бонусы
        if ($this->headerdata['paydisc'] > 0) {
            $customer = \App\Entity\Customer::load($this->customer_id);
            if ($customer->discount > 0) {
                return; //процент
            } else {
                $customer->bonus = $customer->bonus - ($this->headerdata['paydisc'] > 0 ? $this->headerdata['paydisc'] : 0 );
                $customer->save();
            }
        }

        $this->payed = 0;
        if ($this->headerdata['payment'] > 0 && $this->headerdata['payed']) {
            \App\Entity\Pay::addPayment($this->document_id, 1, $this->headerdata['payed'], $this->headerdata['payment'], \App\Entity\Pay::PAY_BASE_OUTCOME, $this->headerdata['paynotes']);
            $this->payed = $this->headerdata['payed'];
        }

        return true;
    }

    public function getRelationBased() {
        $list = array();
        $list['Warranty'] = 'Гарантийный талон';
        $list['ReturnIssue'] = 'Возврат';

        return $list;
    }

    protected function getNumberTemplate() {
        return 'РН-000000';
    }

}
