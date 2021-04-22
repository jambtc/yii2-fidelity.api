<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

use yii\web\Response;

use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\httpclient\Client;

use app\models\Logs;


class ApiLog extends Component
{
    /**
     * Salva il log dell'applicazione
     * @param string $app L'applicazione che richiama il log
     * @param string $controller Il Controller
     * @param string $action Azione del controller
     * @param string $description Descrizione operazione
     * @param string $die true/false Impone l'arresto dell'applicazione
    */
    public function save($app, $controller, $action, $description, $die=false)
    {
        $timestamp = time();
        $id_user = 1; // 1st administrator
        $remoteAddress = self::get_client_ip_server();
        $browser = 'localhost';

        if ((isset(Yii::$app->user)) && !Yii::$app->user->isGuest)
            $id_user = Yii::$app->user->id;

        if (isset($_SERVER['HTTP_USER_AGENT']))
            $browser = $_SERVER['HTTP_USER_AGENT'];

        $model = new Logs;
        $model->timestamp = $timestamp;
        $model->id_user = $id_user;
        $model->remote_address = $remoteAddress;
        $model->browser = $browser;
        $model->app = $app;
        $model->controller = $controller;
        $model->action = $action;
        $model->description = $description;
        $model->die = ($die === true ? 1 : 0);

        if (!$model->save()){
             echo Json::encode($model->errors);
             die();
        }

        if ($die){
            echo Json::encode([
                'success' => false,
                "error"=>$description
            ]);
            die();
        }

        return (object) $model->attributes;
    }

    // Function to get the client ip address
    private function get_client_ip_server() {
        $ipaddress = '';
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(array_key_exists('HTTP_X_FORWARDED', $_SERVER))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(array_key_exists('HTTP_FORWARDED_FOR', $_SERVER))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(array_key_exists('HTTP_FORWARDED', $_SERVER))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(array_key_exists('REMOTE_ADDR', $_SERVER))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';

        return $ipaddress;
    }

}
