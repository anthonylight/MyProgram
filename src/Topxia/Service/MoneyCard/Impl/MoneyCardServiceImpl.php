<?php

namespace Topxia\Service\MoneyCard\Impl;

use Topxia\Common\ArrayToolkit;
use Topxia\Service\Common\BaseService;
use Topxia\Service\Common\ServiceKernel;

class MoneyCardServiceImpl extends BaseService
{
    public function getMoneyCard($id, $lock = false)
    {
        return $this->getMoneyCardDao()->getMoneyCard($id, $lock);
    }

    public function getMoneyCardByIds($ids)
    {
        return $this->getMoneyCardDao()->getMoneyCardByIds($ids);
    }

    public function getMoneyCardByPassword($password)
    {
        return $this->getMoneyCardDao()->getMoneyCardByPassword($password);
    }

    public function getBatch($id)
    {
        return $this->getMoneyCardBatchDao()->getBatch($id);
    }

    public function searchMoneyCards(array $conditions, array $oderBy, $start, $limit)
    {
        return $this->getMoneyCardDao()->searchMoneyCards($conditions, $oderBy, $start, $limit);
    }

    public function searchMoneyCardsCount(array $conditions)
    {
        return $this->getMoneyCardDao()->searchMoneyCardsCount($conditions);
    }

    public function searchBatchs(array $conditions, array $oderBy, $start, $limit)
    {
        return $this->getMoneyCardBatchDao()->searchBatchs($conditions, $oderBy, $start, $limit);
    }

    public function searchBatchsCount(array $conditions)
    {
        return $this->getMoneyCardBatchDao()->searchBatchsCount($conditions);
    }

    public function createMoneyCard(array $moneyCardData)
    {
        $batch = ArrayToolkit::parts($moneyCardData, array(
            'money',
            'coin',
            'cardPrefix',
            'cardLength',
            'number',
            'note',
            'deadline',
            'batchName'
        ));

        if (isset($batch['money'])) {
            $batch['money'] = (int) $batch['money'];
        }

        if (isset($batch['coin'])) {
            $batch['coin'] = (int) $batch['coin'];
        }

        if (isset($batch['cardLength'])) {
            $batch['cardLength'] = (int) $batch['cardLength'];
        }

        if (isset($batch['number'])) {
            $batch['number'] = (int) $batch['number'];
        }

        if (isset($batch['money']) && $batch['money'] <= 0) {
            throw $this->createServiceException('ERROR! Money Value Less Than Zero!');
        }

        if (isset($batch['coin']) && $batch['coin'] <= 0) {
            throw $this->createServiceException('ERROR! Coin Value Less Than Zero!');
        }

        if (isset($batch['cardLength']) && $batch['cardLength'] <= 0) {
            throw $this->createServiceException('ERROR! CardLength Less Than Zero!');
        }

        if (isset($batch['number']) && $batch['number'] <= 0) {
            throw $this->createServiceException('ERROR! Card Number Less Than Zero!');
        }

        $batch['rechargedNumber'] = 0;
        $batch['userId']          = $this->getCurrentUser()->id;
        $batch['createdTime']     = time();
        $batch['deadline']        = date("Y-m-d", strtotime($batch['deadline']));

        $moneyCardIds = $this->makeRands($batch['cardLength'], $batch['number'], $batch['cardPrefix'], $moneyCardData['passwordLength']);

        if (!$this->getMoneyCardDao()->isCardIdAvaliable(array_keys($moneyCardIds))) {
            throw $this->createServiceException('???????????????????????????????????????????????????');
        }

        $token = $this->getTokenService()->makeToken('money_card', array(
            'duration' => strtotime($batch['deadline']) + 24 * 60 * 60 - time()
        ));
        $batch['token'] = $token['token'];
        $batch          = $this->getMoneyCardBatchDao()->addBatch($batch);
        $moneyCards     = array();

        foreach ($moneyCardIds as $cardid => $cardPassword) {
            $moneyCards[] = array(
                'cardId'     => $cardid,
                'password'   => $cardPassword,
                'deadline'   => date('Y-m-d', strtotime($moneyCardData['deadline'])),
                'cardStatus' => 'normal',
                'batchId'    => $batch['id']
            );
        }

        $this->getMoneyCardDao()->addMoneyCard($moneyCards);
        $this->getLogService()->info('money_card_batch', 'create', "????????????????????????,???????????????({$batch['cardPrefix']}),?????????({$batch['id']})");

        return $batch;
    }

    public function lockMoneyCard($id)
    {
        $moneyCard = $this->getMoneyCard($id);

        if (empty($moneyCard)) {
            throw $this->createServiceException('????????????????????????????????????');
        }

        if ($moneyCard['cardStatus'] == 'normal' || $moneyCard['cardStatus'] == 'receive') {
            if ($moneyCard['cardStatus'] == 'receive') {
                $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

                $batch = $this->getBatch($moneyCard['batchId']);

                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'invalid'));

                $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'??????????????????????????????????????????????????????????????????';

                $this->getNotificationService()->notify($card['userId'], 'default', $message);
            }

            $moneyCard = $this->getMoneyCardDao()->updateMoneyCard($moneyCard['id'], array('cardStatus' => 'invalid'));

            $this->getLogService()->info('money_card', 'lock', "??????????????????{$moneyCard['cardId']}????????????");
        } else {
            throw $this->createServiceException('??????????????????????????????????????????');
        }

        return $moneyCard;
    }

    public function unlockMoneyCard($id)
    {
        $moneyCard = $this->getMoneyCard($id);

        if (empty($moneyCard)) {
            throw $this->createServiceException('????????????????????????????????????');
        }

        $batch = $this->getBatch($moneyCard['batchId']);

        if ($batch['batchStatus'] == 'invalid') {
            throw $this->createServiceException('???????????????????????????????????????????????????????????????????????????????????????????????????');
        }

        if ($moneyCard['cardStatus'] == 'invalid') {
            $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

            if (!empty($card)) {
                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'receive'));
                $this->updateMoneyCard($card['cardId'], array('cardStatus' => 'receive'));
                $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'???????????????????????????????????????';

                $this->getNotificationService()->notify($card['userId'], 'default', $message);
            } else {
                $moneyCard = $this->getMoneyCardDao()->updateMoneyCard($moneyCard['id'], array('cardStatus' => 'normal'));
            }

            $this->getLogService()->info('money_card', 'unlock', "??????????????????{$moneyCard['cardId']}????????????");
        } else {
            throw $this->createServiceException("???????????????????????????????????????{$moneyCard['cardStatus']}--{$moneyCard['rechargeUserId']}");
        }

        return $moneyCard;
    }

    public function deleteMoneyCard($id)
    {
        $moneyCard = $this->getMoneyCard($id);
        $batch     = $this->getBatch($moneyCard['batchId']);
        $this->getMoneyCardDao()->deleteMoneyCard($id);
        $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

        if (!empty($card)) {
            $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'deleted'));

            $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'??????????????????????????????????????????????????????????????????';

            $this->getNotificationService()->notify($card['userId'], 'default', $message);
        }

        $this->getLogService()->info('money_card', 'delete', "??????????????????{$moneyCard['cardId']}????????????");
        // if ($moneyCard['cardStatus'] != 'recharged') {
        //     $this->getMoneyCardDao()->deleteMoneyCard($id);

        //     $this->getLogService()->info('money_card', 'delete', "??????????????????{$moneyCard['cardId']}????????????");
        // } else {
        //     throw $this->createServiceException('???????????????????????????????????????');
        // }
    }

    public function lockBatch($id)
    {
        $batch = $this->getBatch($id);

        if (empty($batch)) {
            throw $this->createServiceException('?????????????????????????????????');
        }

        $this->getMoneyCardDao()->updateBatchByCardStatus(
            array(
                'batchId'    => $batch['id'],
                'cardStatus' => 'normal'
            ),
            array('cardStatus' => 'invalid')
        );

        $moneyCards = $this->searchMoneyCards(
            array(
                'batchId'    => $batch['id'],
                'cardStatus' => 'receive'
            ),
            array('id', 'ASC'),
            0,
            1000
        );

        foreach ($moneyCards as $moneyCard) {
            $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

            if (!empty($card)) {
                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'invalid'));

                $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'??????????????????????????????????????????????????????????????????';

                $this->getNotificationService()->notify($card['userId'], 'default', $message);
            }
        }

        $this->getMoneyCardDao()->updateBatchByCardStatus(
            array(
                'batchId'    => $batch['id'],
                'cardStatus' => 'receive'
            ),
            array('cardStatus' => 'invalid')
        );

        $batch = $this->updateBatch($batch['id'], array('batchStatus' => 'invalid'));
        $this->getLogService()->info('money_card_batch', 'lock', "??????????????????{$batch['id']}????????????");

        return $batch;
    }

    public function unlockBatch($id)
    {
        $batch = $this->getBatch($id);

        if (empty($batch)) {
            throw $this->createServiceException('?????????????????????????????????');
        }

        $moneyCards = $this->searchMoneyCards(
            array(
                'batchId'    => $batch['id'],
                'cardStatus' => 'invalid'
            ),
            array('id', 'ASC'),
            0,
            1000
        );

        $this->getMoneyCardDao()->updateBatchByCardStatus(
            array(
                'batchId'        => $batch['id'],
                'cardStatus'     => 'invalid',
                'rechargeUserId' => 0
            ),
            array('cardStatus' => 'normal')
        );

        foreach ($moneyCards as $moneyCard) {
            $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

            if (!empty($card) && $card['status'] == 'invalid') {
                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'receive'));
                $this->updateMoneyCard($card['cardId'], array('cardStatus' => 'receive'));
                $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'???????????????????????????????????????';

                $this->getNotificationService()->notify($card['userId'], 'default', $message);
            }
        }

        $batch = $this->updateBatch($batch['id'], array('batchStatus' => 'normal'));
        $this->getLogService()->info('money_card_batch', 'unlock', "??????????????????{$batch['id']}????????????");

        return $batch;
    }

    public function deleteBatch($id)
    {
        $batch = $this->getBatch($id);

        if (empty($batch)) {
            throw $this->createServiceException(sprintf('???????????????????????????????????????'));
        }

        $moneyCards = $this->getMoneyCardDao()->searchMoneyCards(array('batchId' => $id), array('id', 'ASC'), 0, 1000);

        $this->getMoneyCardBatchDao()->deleteBatch($id);
        $this->getMoneyCardDao()->deleteMoneyCardsByBatchId($id);

        foreach ($moneyCards as $moneyCard) {
            $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

            if (!empty($card)) {
                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array('status' => 'deleted'));

                $message = '?????????????????????'.$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????").'??????????????????????????????????????????????????????????????????';

                $this->getNotificationService()->notify($card['userId'], 'default', $message);
            }
        }

        $this->getLogService()->info('money_card_batch', 'delete', "??????????????????{$id}????????????");
    }

    protected function makeRands($median, $number, $cardPrefix, $passwordLength)
    {
        if ($median <= 3) {
            throw new \RuntimeException('Bad median');
        }

        $cardIds = array();
        $i       = 0;

        while (true) {
            $id = '';

            for ($j = 0; $j < (int) $median - 3; ++$j) {
                $id .= mt_rand(0, 9);
            }

            $tmpId = $cardPrefix.$id;
            $id    = $this->blendCrc32($tmpId);

            if (!isset($cardIds[$id])) {
                $tmpPassword                      = $this->makePassword($passwordLength);
                $cardIds[$id]                     = $tmpPassword;
                $this->tmpPasswords[$tmpPassword] = true;
                ++$i;
            }

            if ($i >= $number) {
                break;
            }
        }

        return $cardIds;
    }

    public function uuid($uuidLength, $prefix = '', $needSplit = false)
    {
        $chars = md5(uniqid(mt_rand(), true));

        if ($needSplit) {
            $uuid = '';
            $uuid .= substr($chars, 0, 8).'-';
            $uuid .= substr($chars, 8, 4).'-';
            $uuid .= substr($chars, 12, 4).'-';
            $uuid .= substr($chars, 16, 4).'-';
            $uuid .= substr($chars, 20, 12);
        } else {
            $uuid = substr($chars, 0, $uuidLength);
        }

        return $prefix.$uuid;
    }

    public function blendCrc32($word)
    {
        return $word.substr(crc32($word), 0, 3);
    }

    public function checkCrc32($word)
    {
        return substr(crc32(substr($word, 0, -3)), 0, 3) == substr($word, -3, 3);
    }

    private $tmpPasswords = array();
    protected function makePassword($length)
    {
        while (true) {
            $uuid      = $this->uuid($length - 3);
            $password  = $this->blendCrc32($uuid);
            $moneyCard = $this->getMoneyCardByPassword($password);

            if (($moneyCard == null) && (!isset($this->tmpPasswords[$password]))) {
                break;
            }
        }

        return $password;
        //NEED TO CHECK Unique

        // $cardIds[$id] = $this->makePassword($passwordLength);

        // $pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
        // $password = chr(rand(97, 122));
        // for ($j=0; $j < ((int)$length)-1; $j++) {
        //         $password .= $pattern[mt_rand(0, 35)];
        //     }

        // return $password;
    }

    public function updateBatch($id, $fields)
    {
        return $this->getMoneyCardBatchDao()->updateBatch($id, $fields);
    }

    public function updateMoneyCard($id, $fields)
    {
        return $this->getMoneyCardDao()->updateMoneyCard($id, $fields);
    }

    public function useMoneyCard($id, $fields)
    {
        $connection = ServiceKernel::instance()->getConnection();

        try {
            $connection->beginTransaction();

            $moneyCard = $this->getMoneyCard($id, true);

            if ($moneyCard['cardStatus'] == 'recharged') {
                $connection->rollback();
                return $moneyCard;
            }

            $moneyCard = $this->updateMoneyCard($id, $fields);

            $batch = $this->getBatch((int) $moneyCard['batchId']);

            $flow = array(
                'userId'   => $fields['rechargeUserId'],
                'amount'   => $batch['coin'],
                'name'     => '?????????'.$moneyCard['cardId'].'??????'.$batch['coin'],
                'orderSn'  => '',
                'category' => 'inflow',
                'note'     => ''
            );

            $this->getCashService()->inflowByCoin($flow);
            $batch['rechargedNumber'] += 1;
            $this->updateBatch($batch['id'], $batch);
            $card = $this->getCardService()->getCardByCardIdAndCardType($moneyCard['id'], 'moneyCard');

            if (!empty($card)) {
                $this->getCardService()->updateCardByCardIdAndCardType($moneyCard['id'], 'moneyCard', array(
                    'status'  => 'used',
                    'useTime' => $moneyCard['rechargeTime']
                ));
            } else {
                $this->getCardService()->addCard(array(
                    'cardId'      => $moneyCard['id'],
                    'cardType'    => 'moneyCard',
                    'status'      => 'used',
                    'deadline'    => strtotime($moneyCard['deadline']),
                    'useTime'     => $moneyCard['rechargeTime'],
                    'userId'      => $moneyCard['rechargeUserId'],
                    'createdTime' => time()
                ));
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }

        return $moneyCard;
    }

    public function receiveMoneyCard($token, $userId)
    {
        $token = $this->getTokenService()->verifyToken('money_card', $token);

        if (!$token) {
            return array(
                'code'    => 'failed',
                'message' => '???????????????'
            );
        }

        try {
            $this->getMoneyCardBatchDao()->getConnection()->beginTransaction();
            $batch = $this->getMoneyCardBatchDao()->getBatchByToken($token['token'], true);

            if (empty($batch)) {
                $this->getMoneyCardBatchDao()->getConnection()->commit();

                return array(
                    'code'    => 'failed',
                    'message' => '?????????????????????????????????'
                );
            }

            if ($batch['batchStatus'] == 'invalid') {
                $this->getMoneyCardBatchDao()->getConnection()->commit();

                return array(
                    'code'    => 'failed',
                    'message' => '????????????????????????'
                );
            }

            if (!empty($userId)) {
                $conditions = array(
                    'rechargeUserId' => $userId,
                    'batchId'        => $batch['id']
                );

                $moneyCard = $this->getMoneyCardDao()->searchMoneyCards($conditions, array('id', 'DESC'), 0, 1);

                if (!empty($moneyCard)) {
                    $this->getMoneyCardBatchDao()->getConnection()->commit();

                    return array(
                        'code'    => 'failed',
                        'message' => '??????????????????????????????'
                    );
                }
            }

            $conditions = array(
                'rechargeUserId' => 0,
                'cardStatus'     => 'normal',
                'batchId'        => $batch['id']
            );
            $moneyCards = $this->getMoneyCardDao()->searchMoneyCards($conditions, array('id', 'ASC'), 0, 1);

            if (empty($moneyCards)) {
                $this->getMoneyCardBatchDao()->getConnection()->commit();

                return array(
                    'code'    => 'failed',
                    'message' => '??????????????????????????????'
                );
            }

            $moneyCard = $this->getMoneyCardDao()->getMoneyCard($moneyCards[0]['id']);

            if (!empty($moneyCard) && !empty($userId)) {
                $moneyCard = $this->getMoneyCardDao()->updateMoneyCard($moneyCard['id'], array(
                    'rechargeUserId' => $userId,
                    'cardStatus'     => 'receive',
                    'receiveTime'    => time()
                ));

                if (empty($moneyCard)) {
                    $this->getMoneyCardBatchDao()->getConnection()->commit();

                    return array(
                        'code'    => 'failed',
                        'message' => '?????????????????????'
                    );
                }

                $this->getCardService()->addCard(array(
                    'cardId'   => $moneyCard['id'],
                    'cardType' => 'moneyCard',
                    'deadline' => strtotime($moneyCard['deadline']),
                    'userId'   => $userId
                ));
                $message = "?????????????????????".$batch['coin'].$this->getSettingService()->get("coin.coin_name", "?????????")."????????????????????????";
                $this->getNotificationService()->notify($userId, 'default', $message);
            }

            $this->getMoneyCardBatchDao()->getConnection()->commit();

            return array(
                'id'      => $moneyCard['id'],
                'code'    => 'success',
                'message' => '????????????????????????????????????'
            );
        } catch (\Exception $e) {
            $this->getMoneyCardBatchDao()->getConnection()->rollback();
            throw $e;
        }
    }

    protected function getMoneyCardDao()
    {
        return $this->createDao('MoneyCard.MoneyCardDao');
    }

    protected function getCardService()
    {
        return $this->createService('Card.CardService');
    }

    protected function getMoneyCardBatchDao()
    {
        return $this->createDao('MoneyCard.MoneyCardBatchDao');
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');
    }

    protected function getCashService()
    {
        return $this->createService('Cash.CashService');
    }

    private function getTokenService()
    {
        return $this->createService('User.TokenService');
    }

    private function getSettingService()
    {
        return $this->createService('System.SettingService');
    }

    private function getNotificationService()
    {
        return $this->createService('User.NotificationService');
    }
}
