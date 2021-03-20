<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "np_stores".
 *
 * @property int $id_store
 * @property int $id_merchant
 * @property string $denomination
 * @property string $address
 * @property string $city
 * @property string $county
 * @property string $cap
 * @property string $vat
 * @property string $bps_storeid
 * @property int $deleted
 */
class Stores extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'np_stores';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_merchant', 'denomination', 'address', 'city', 'county', 'cap', 'vat'], 'required'],
            [['id_merchant', 'deleted'], 'integer'],
            [['denomination', 'address', 'city', 'county', 'cap', 'vat'], 'string', 'max' => 250],
            [['bps_storeid'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_store' => Yii::t('app', 'Id Store'),
            'id_merchant' => Yii::t('app', 'Id Merchant'),
            'denomination' => Yii::t('app', 'Denomination'),
            'address' => Yii::t('app', 'Address'),
            'city' => Yii::t('app', 'City'),
            'county' => Yii::t('app', 'County'),
            'cap' => Yii::t('app', 'Cap'),
            'vat' => Yii::t('app', 'Vat'),
            'bps_storeid' => Yii::t('app', 'Bps Storeid'),
            'deleted' => Yii::t('app', 'Deleted'),
        ];
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\StoresQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\StoresQuery(get_called_class());
    }
}
