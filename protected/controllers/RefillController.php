<?php
/**
 * Acoes do modulo "Refill".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author  Adilson Leffa Magnus.
 * @copyright   Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 23/06/2012
 */

class RefillController extends Controller
{
    public $attributeOrder = 'date DESC';
    public $extraValues    = array('idUser' => 'username');

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );
    public $fieldsInvisibleClient = array(
        'id_user',
        'idUserusername',
        'refill_type',
    );

    public function init()
    {
        $this->instanceModel = new Refill;
        $this->abstractModel = Refill::model();
        $this->titleReport   = Yii::t('yii', 'Refill');
        parent::init();
    }

    public function beforeSave($values)
    {
        if (isset(Yii::app()->session['isAgent']) && Yii::app()->session['isAgent'] == true) {

            if (Yii::app()->session['id_user'] == $values['id_user']) {
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'You cannot add credit to yourself'),
                ));
                exit;
            }
            //get the total credit betewen agent users
            $modelUser = User::model()->find(array(
                'select'    => 'SUM(credit) AS credit',
                'condition' => 'id_user = :key',
                'params'    => array(':key' => Yii::app()->session['id_user']),
            )
            );

            $totalRefill = $modelUser->credit + $values['credit'];

            $modelUser = User::model()->findByPk((int) Yii::app()->session['id_user']);

            $userAgent = $modelUser->typepaid == 1 ? $modelUser->credit = $modelUser->credit + $modelUser->creditlimit : $modelUser->credit;

            $maximunCredit = $this->config["global"]['agent_limit_refill'] * $userAgent;
            //Yii::log("$totalRefill > $maximunCredit", 'info');
            if ($totalRefill > $maximunCredit) {
                $limite = $maximunCredit - $totalRefill;
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'Limit refill exceeded, your limit is') . ' ' . $maximunCredit . '. ' . Yii::t('yii', 'You have') . ' ' . $limite . ' ' . Yii::t('yii', 'to refill'),
                ));
                exit;
            }
        }

        return $values;
    }

    public function afterSave($model, $values)
    {
        if ($this->isNewRecord) {
            UserCreditManager::releaseUserCredit($model->id_user, $model->credit, $model->description, 2);
        }
        return;
    }

    public function recordsExtraSum($records)
    {
        $criteria = new CDbCriteria(array(
            'select'    => 'EXTRACT(YEAR_MONTH FROM date) AS CreditMonth , SUM(t.credit) AS sumCreditMonth',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
            'order'     => $this->order,
            'limit'     => $this->limit,
            'offset'    => $this->start,
            'group'     => 'CreditMonth',
        ));

        $this->nameSum = 'sum';

        return $this->abstractModel->findAll($criteria);
    }

    public function setAttributesModels($attributes, $models)
    {

        $modelRefill = $this->abstractModel->find(array(
            'select'    => 'SUM(t.credit) AS credit',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
        ));

        $modelRefillSumm2 = $this->abstractModel->findAll(array(
            'select'    => 'EXTRACT(YEAR_MONTH FROM date) AS CreditMonth , SUM(t.credit) AS sumCreditMonth',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
            'group'     => 'CreditMonth',
        ));

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            $attributes[$i]['sumCredit']      = number_format($modelRefill->credit, 2);
            $attributes[$i]['sumCreditMonth'] = $modelRefillSumm2[0]['sumCreditMonth'];
            $attributes[$i]['CreditMonth']    = substr($modelRefillSumm2[0]['CreditMonth'], 0, 4) . '-' . substr($modelRefillSumm2[0]['CreditMonth'], -2);
        }
        return $attributes;
    }
}
