<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\ReRequests;
use app\components\WebApp;
use app\components\Settings;
use app\components\ApiLog;

use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\RequestEvent;

/**
 *
 * @author Sergio Casizzone <jambtc@gmail.com>
 * @since 2.0
 */
class RequestController extends Controller
{
    public $id;

    public function options($actionID)
    {
        return ['id'];
    }

    // scrive a video
    private function log($text, $die=false){
        $log = new ApiLog;
        $time = "\r\n" .date('Y/m/d h:i:s a - ', time());
        echo  $time.$text;
        $log->save('api.command','request','index', $time.$text, $die);
    }

    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex()
    {
        set_time_limit(0); //imposto il time limit unlimited

        $try = 32;
		$MAXtry = 32768; // quasi 1 giorno di monitoraggio

		$this->log("Checking request $this->id");
        //$log->save('api.command','request','index','Store account found: id # '.$store->id_store);

        //carico la richiesta
        $model = ReRequests::findOne(WebApp::decrypt($this->id));
        if($model===null)
            $this->log('Error. The requested id does not exist.',true);

        $this->log("Request loaded. Status is $model->sent");
        $rulesApiKeys = Settings::rulesApiKeys();
        $this->log("rules api key is: <pre>".print_r($rulesApiKeys->attributes,true)."</pre>");
        //exit;

        // INIZIO IL LOOP
        while(true){
            if ($model->sent == 0){ //se il valore è 0 proseguo
                // set nonce
                $microtime = explode(' ', microtime());
                $nonce = $microtime[1] . str_pad(substr($microtime[0], 2, 6), 6, '0');

                $payload = json_decode($model->payload);
                $payload->event->nonce = $nonce;

                // build the POST data string
                $postdata = http_build_query($payload, '', '&');

                // set API key and sign the message
                $sign = hash_hmac('sha512', hash('sha256', $nonce . $postdata, true), base64_decode(WebApp::decrypt($rulesApiKeys->secret_key)), true);

                $headers = array(
                  'API-Key: ' . $rulesApiKeys->public_key,
                  'API-Sign: ' . base64_encode($sign),
                  'x-fre-origin: '. $payload->event->merchant_id,
                  'Authorization: ' . $rulesApiKeys->public_key,
                  'Content-Type: application/json',
                  'accept: application/json',
                );

                $jsonpayload = json_encode($payload);

                $this->log("headers are: <pre>" . print_r($headers, true) . "</pre>");
                $this->log("json payload is: <pre>" . print_r($jsonpayload, true) . "</pre>");
                $this->log("payload is: <pre>".print_r($payload,true)."</pre>");
                // exit;

                $client = new Client();
                $request = $client->createRequest()
                    ->setMethod('POST')
                    ->setFormat(Client::FORMAT_JSON)
                    ->setUrl($rulesApiKeys->url)
                    ->setData($jsonpayload)
                    ->setHeaders($headers);


                $request->send();

                $response = $request->getData();
                // $this->log("dump Response is: <pre>" . var_dump($response) . "</pre>");
                $this->log("json Response is: <pre>".print_r($response,true)."</pre>");
                $this->log("array Response is: <pre>".print_r(json_decode($response,true),true)."</pre>");
                // exit;
                if ($this->analisi($response)){
                    $model->sent = 1;
                    $model->save();
                    break;
                }
                // continue loop
            }else if ($model->sent == 1){
				$this->log('Payload already sent!');
				break;
			}else{
				$this->log('Payload already sent, but there was an unknown error!');
				break;
			}

            $this->log("Request id: $this->id, Status: ".$model->sent.", Waiting seconds: ".$try."\n");
			sleep($try);
			$try = $try*2;

			if ($try > $MAXtry){
				// imposto il sent to error
				$model->sent = 2;
				$model->save();
				$this->log('Payload '.$this->id.' is not monitored anymore.');
				break;
			}
        }
        return ExitCode::OK;
    }

    private function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function analisi($response){
        $return = false;
        if ($this->isJson($response)){
            $analisi = json_decode($response, true); // to array
        }
        if (is_array($analisi)){
            if (!isset($analisi['errors'])){
                foreach ($analisi['event'] as $id => $group){
                    $this->log("Group array is: <pre>".print_r($group,true)."</pre>");
                    if (is_array($group)){
                        foreach ($group as $id => $rules){
                            $this->log("Rules array is: <pre>".print_r($rules,true)."</pre>");
                            if (is_array($rules)){
                                foreach ($rules as $id => $rule){
                                    $this->log("Rule array is: <pre>".print_r($rule,true)."</pre>");
                                    if ($rule == 'ok'){
                                        $this->log('Payload sent correctly!');
                                    } else {
                                        $this->log('Payload sent, but cannot trigger event!');
                                    }
                                }
                            } else {
                                if ($rules == 'ok'){
                                    $this->log('Payload sent correctly!');
                                } else {
                                    $this->log('Payload sent, but cannot trigger event!');
                                }
                            }
                        }
                    } else {
                        if ($group == 'ok'){
                            $this->log('Payload sent correctly!');
                        } else {
                            $this->log('Payload sent, but cannot trigger event!');
                        }
                    }
                }
            }else{
                $this->log('Payload sent, but there was an error:'. $analisi['errors']['detail']);
            }
            $return = true;
        }else{
            $this->log('Error! Response is not json, retry again. <pre>'.print_r($response,true).'</pre>');
        }
        return $return;
    }
}
