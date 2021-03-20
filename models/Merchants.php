<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "np_merchants".
 *
 * @property int $id_merchant
 * @property string $id_user
 * @property string $denomination
 * @property string $vat
 * @property string $address
 * @property string $city
 * @property string $county
 * @property string $cap
 * @property int $deleted
 */
class Merchants extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'np_merchants';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'denomination', 'vat', 'address', 'city', 'county', 'cap'], 'required'],
            [['deleted'], 'integer'],
            [['id_user', 'denomination', 'vat', 'address', 'city', 'county', 'cap'], 'string', 'max' => 250],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_merchant' => Yii::t('app', 'Id Merchant'),
            'id_user' => Yii::t('app', 'Id User'),
            'denomination' => Yii::t('app', 'Denomination'),
            'vat' => Yii::t('app', 'Vat'),
            'address' => Yii::t('app', 'Address'),
            'city' => Yii::t('app', 'City'),
            'county' => Yii::t('app', 'County'),
            'cap' => Yii::t('app', 'Cap'),
            'deleted' => Yii::t('app', 'Deleted'),
        ];
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\MerchantsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\MerchantsQuery(get_called_class());
    }
}
