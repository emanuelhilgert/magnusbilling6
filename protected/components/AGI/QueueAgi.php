<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */

class QueueAgi
{
    public function callQueue(&$agi, &$MAGNUS, &$Calc, $modelDestination, &$DidAgi = null, $type = 'queue')
    {
        $agi->verbose("Queue module", 5);

        if ($modelDestination->idDid->cbr == 1) {
            CallbackAgi::advanced0800CallBack($agi, $MAGNUS, $modelDestination);
        }

        $agi->answer();
        $startTime           = time();
        $MAGNUS->destination = $modelDestination->idDid->did;
        $modelQueue          = Queue::model()->findByPk($modelDestination->id_queue);

        $agi->set_variable("UNIQUEID", $MAGNUS->uniqueid);
        $agi->set_variable("QUEUCALLERID", $MAGNUS->CallerID);
        $agi->set_variable("IDQUEUE", $modelQueue->id);
        $agi->set_variable("USERNAME", $modelQueue->idUser->username);
        $agi->set_variable('CHANNEL(language)', $modelQueue->language);

        $queueName = $modelQueue->name;
        $agi->verbose($queueName);
        Queue::model()->insertQueueStatus($modelQueue->id, $MAGNUS->uniqueid, $queueName, $MAGNUS->CallerID, $MAGNUS->channel);

        $agi->execute("Queue", $queueName . ',tc,,,,' . Yii::app()->baseUrl . '/agi.php');

        $MAGNUS->stopRecordCall($agi);

        Queue::model()->deleteQueueStatus($MAGNUS->uniqueid);

        $stopTime = time();

        $answeredtime = $stopTime - $startTime;

        $siptransfer = $agi->get_variable("SIPTRANSFER");

        $linha = exec(" egrep $MAGNUS->uniqueid /var/log/asterisk/queue_log | tail -1");
        $linha = explode('|', $linha);

        $agi->verbose(print_r($linha, true), 25);

        if ($linha[4] == 'ABANDON' || $linha[4] == 'EXITEMPTY' || $linha[4] == 'EXITWITHTIMEOUT') {
            $terminatecauseid = 7;
        } else {
            $terminatecauseid = 1;
        }

        $agi->verbose('$siptransfer => ' . $siptransfer['data'], 5);
        if ($siptransfer['data'] != 'yes' && $type == 'queue') {
            $modelPrefix = Prefix::model()->find("prefix = SUBSTRING(:key,1,length(prefix))",
                array(':key' => $MAGNUS->destination));

            $modelCall                   = new Call();
            $modelCall->uniqueid         = $MAGNUS->uniqueid;
            $modelCall->sessionid        = $MAGNUS->channel;
            $modelCall->id_user          = $modelQueue->id_user;
            $modelCall->starttime        = gmdate("Y-m-d H:i:s", time() - $answeredtime);
            $modelCall->sessiontime      = $answeredtime;
            $modelCall->real_sessiontime = intval($answeredtime);
            $modelCall->calledstation    = $MAGNUS->destination;
            $modelCall->terminatecauseid = $terminatecauseid;
            $modelCall->stoptime         = date('Y-m-d H:i:s');
            $modelCall->sessionbill      = $sell_price;
            $modelCall->id_plan          = $modelQueue->idUser->id_plan;
            $modelCall->id_trunk         = null;
            $modelCall->src              = $MAGNUS->CallerID;
            $modelCall->sipiax           = 8;
            $modelCall->buycost          = 0;
            $modelCall->id_prefix        = $modelPrefix->id;
            $modelCall->save();
            $modelError = $modelCall->getErrors();
            if (count($modelError)) {
                $agi->verbose(print_r($modelError, true), 25);
            }

        }
        if ($type == 'queue') {
            $MAGNUS->hangup($agi);
            exit;
        } else {
            return;
        }

    }

    public function recIvrQueue($agi, $MAGNUS, $Calc, $result_did)
    {

        $agi->verbose('recIvrQueue');
        $operator = preg_replace("/SIP\//", "", $agi->get_variable("MEMBERNAME", true));

        $MAGNUS->uniqueid    = $agi->get_variable("UNIQUEID", true);
        $MAGNUS->destination = $agi->request['agi_extension'];
        $username            = $agi->get_variable("USERNAME", true);
        $id_queue            = $agi->get_variable("IDQUEUE", true);
        $callerid            = $agi->get_variable("QUEUCALLERID", true);
        $oldtime             = $agi->get_variable("QEHOLDTIME", true);

        Queue::model()->updateQueueStatus($operator, $id_queue, $oldtime, $MAGNUS->uniqueid);

        $agi->verbose("\n\n" . $MAGNUS->uniqueid . " $operator ATENDEU A CHAMADAS\n\n", 6);

        $modelUser           = Sip::model()->find('name = :key', array(':key' => $operator));
        $MAGNUS->record_call = $modelUser->idUser->record_call;
        $MAGNUS->record_call = $modelUser->record_call;

        $MAGNUS->startRecordCall($agi);
        exit;
    }
}
