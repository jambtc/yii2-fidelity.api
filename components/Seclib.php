<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use \phpseclib;

use app\components\WebApp;
use app\components\Settings;

/**
 * @author Sergio Casizzone
 * Esegue in background programmi e comandi per windows e linux
 *
 * utilizza la nuova libreria phpseclib 2.0
 * installata con composer require phpseclib/phpseclib:~2.0
 *
 * [Browse Git](https://github.com/phpseclib/phpseclib)
 */
class Seclib extends Component
{
    public function execInBackground($cmd)
    {
        if (substr(php_uname(), 0, 7) == "Windows"){
            pclose(popen("start /B ". $cmd, "r"));
        } else {
            // determine whether we are running in a docker container
            if (isset($_ENV['DOCKERCONTAINER']) && $_ENV['DOCKERCONTAINER'] == true){
                $host_address = $_SERVER['REMOTE_ADDR'];
                $port = '2222';
            } else {
                $host_address = 'localhost';
                $port = '22';
            }

            $ssh = new \phpseclib\Net\SSH2($host_address, $port);
            if (!$ssh->login(WebApp::decrypt(Settings::load()->sshuser), WebApp::decrypt(Settings::load()->sshpassword))) {
                return array('error' => 'Login to localhost server failed');
            }
            $action = $cmd . " > /dev/null &";
            $ssh->exec($action);
        }
    }
}
