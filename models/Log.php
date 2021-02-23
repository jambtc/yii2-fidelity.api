<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "np_log".
 *
 * @property int $id_log
 * @property int $timestamp
 * @property int|null $id_user
 * @property string $remote_address
 * @property string $browser
 * @property string $app
 * @property string $controller
 * @property string $action
 * @property string $description
 * @property int $die
 */
class Log extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'np_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['timestamp', 'remote_address', 'browser', 'app', 'controller', 'action', 'description', 'die'], 'required'],
            [['timestamp', 'id_user', 'die'], 'integer'],
            [['description'], 'string'],
            [['remote_address'], 'string', 'max' => 60],
            [['browser'], 'string', 'max' => 500],
            [['app', 'controller', 'action'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_log' => Yii::t('app', 'Id Log'),
            'timestamp' => Yii::t('app', 'Timestamp'),
            'id_user' => Yii::t('app', 'Id User'),
            'remote_address' => Yii::t('app', 'Remote Address'),
            'browser' => Yii::t('app', 'Browser'),
            'app' => Yii::t('app', 'App'),
            'controller' => Yii::t('app', 'Controller'),
            'action' => Yii::t('app', 'Action'),
            'description' => Yii::t('app', 'Description'),
            'die' => Yii::t('app', 'Die'),
        ];
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\LogQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\LogQuery(get_called_class());
    }
}
