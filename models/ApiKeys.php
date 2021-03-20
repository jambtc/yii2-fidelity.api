<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "np_api".
 *
 * @property int $id_api
 * @property int $id_user
 * @property string $key_public
 * @property string $key_secret
 * @property string $key_description
 *
 * @property NpUsers $user
 */
class ApiKeys extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'np_api';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'key_public', 'key_secret', 'key_description'], 'required'],
            [['id_user'], 'integer'],
            [['key_public'], 'string', 'max' => 50],
            [['key_secret', 'key_description'], 'string', 'max' => 200],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => NpUsers::className(), 'targetAttribute' => ['id_user' => 'id_user']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_api' => Yii::t('app', 'Id Api'),
            'id_user' => Yii::t('app', 'Id User'),
            'key_public' => Yii::t('app', 'Key Public'),
            'key_secret' => Yii::t('app', 'Key Secret'),
            'key_description' => Yii::t('app', 'Key Description'),
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery|\app\models\query\NpUsersQuery
     */
    public function getUser()
    {
        return $this->hasOne(NpUsers::className(), ['id_user' => 'id_user']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\ApiKeysQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\ApiKeysQuery(get_called_class());
    }
}
