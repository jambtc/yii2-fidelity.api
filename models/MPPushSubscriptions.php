<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "mp_subscriptions".
 *
 * @property int $id
 * @property int|null $id_user
 * @property string $type
 * @property string $browser
 * @property string $endpoint
 * @property string $auth
 * @property string $p256dh
 *
 * @property MPUsers $user
 */
class MPPushSubscriptions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'mp_subscriptions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user'], 'integer'],
            [['type', 'browser', 'endpoint', 'auth', 'p256dh'], 'required'],
            [['type'], 'string', 'max' => 20],
            [['browser', 'endpoint', 'auth', 'p256dh'], 'string', 'max' => 1000],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => MPUsers::className(), 'targetAttribute' => ['id_user' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'id_user' => Yii::t('app', 'Id User'),
            'type' => Yii::t('app', 'Type'),
            'browser' => Yii::t('app', 'Browser'),
            'endpoint' => Yii::t('app', 'Endpoint'),
            'auth' => Yii::t('app', 'Auth'),
            'p256dh' => Yii::t('app', 'P256dh'),
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery|\app\models\query\MpUsersQuery
     */
    public function getUser()
    {
        return $this->hasOne(MPUsers::className(), ['id' => 'id_user']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\MPPushSubscriptionsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\MPPushSubscriptionsQuery(get_called_class());
    }
}
