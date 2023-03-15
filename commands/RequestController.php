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
use yii\helpers\Json;

// use yii\httpclient\Client;
// use yii\httpclient\Request;
// use yii\httpclient\RequestEvent;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

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
        /**
         * for testing purpose
         */
        // echo WebApp::encrypt($this->id);
        // exit;

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

                // encode payload
                $jsonpayload = Json::encode($payload, true); //json_encode($payload);



                // get the domain
                $parsed = parse_url($rulesApiKeys->url);
                // $host = $parsed['host'];
                // $port = $parsed['port'];

                


                // set API key and sign the message
                $sign = hash_hmac('sha512', hash('sha256', $nonce . $postdata, true), base64_decode(WebApp::decrypt($rulesApiKeys->secret_key)), true);

                // $headers = array(
                //   'API-Key: ' . $rulesApiKeys->public_key,
                //   'API-Sign: ' . 'iEuxQFAmULsfDIgrv+yLZwNRZYsor0YhkSeX5ByviH6YnuXe30G/YafYKLTRgc6TI9BEpik67cWaF1t+QVVuoQ==', //base64_encode($sign),
                //   'x-fre-origin: '. $payload->event->merchant_id,
                //   'Authorization: ' . $rulesApiKeys->public_key,
                //   'Content-Type: application/json',
                // //   'Accept: application/json',
                // //   'Content-Length: ' . strlen($jsonpayload),
                // //   'Host: '. $host .':'. $port,
                // );
                // $this->log("headers 1 are: <pre>" . print_r($headers, true) . "</pre>");

                $headers = [
                    'API-Key' => $rulesApiKeys->public_key,
                    'API-Sign' => 'iEuxQFAmULsfDIgrv+yLZwNRZYsor0YhkSeX5ByviH6YnuXe30G/YafYKLTRgc6TI9BEpik67cWaF1t+QVVuoQ==',
                    'x-fre-origin' => $payload->event->merchant_id,
                    'Authorization' => $rulesApiKeys->public_key,
                    'Content-Type' => 'application/json'
                ];
                // $this->log("headers 2 are: <pre>" . print_r($headers, true) . "</pre>");
                // exit;

                // $this->log("payload is: <pre>" . print_r($payload, true) . "</pre>");
                // echo "<pre>api secret: ".print_r(WebApp::decrypt($rulesApiKeys->secret_key), true)."</pre>";


                $this->log("headers are: <pre>" . print_r($headers, true) . "</pre>");
                $this->log("json payload is: <pre>" . print_r($jsonpayload, true) . "</pre>");
                $this->log("payload is: <pre>".print_r($payload,true)."</pre>");

                // $this->test($headers, $jsonpayload);
                // exit;
                $client = new Client();
                $request = new Request('POST', $rulesApiKeys->url, $headers, $jsonpayload);

                $result = $client->sendAsync($request)->wait();
                // echo $result->getBody();

                if (null !== $result->getBody()) {
                    $this->log('The Rules Engine responded correctly to the payload submission!');
                    $this->log("Response is: <pre>" . print_r($result->getBody(), true) . "</pre>");

                    $model->sent = 1;
                    $model->save();
                    break;
                } else {
                    $this->log('The response from the Rules Engine is null!');
                } 
                
                
                
                // $promise = $client->sendAsync($request)->then(function ($response)  {
                //     $body = $response->getBody();
                //     if (null !== $body) {
                //         $this->log('The Rules Engine responded correctly to the payload submission!');
                //         $this->log("Response is: <pre>" . print_r($body, true) . "</pre>");

                //         $model->sent = 1;
                //         $model->save();
                //         break;
                //     } else {
                //         $this->log('The response from the Rules Engine is null!');
                //     }  

                //     // $this->log("json Response is: <pre>" . print_r($result, true) . "</pre>");
                //     // $this->log("array Response is: <pre>" . print_r(json_decode($result, true), true) . "</pre>");

                //     // exit;
                // });
                // $promise->wait();
                
                // $client = new Client();
                // $request = $client->createRequest()
                //     ->setMethod('POST')
                //     ->setFormat(Client::FORMAT_JSON)
                //     ->setUrl($rulesApiKeys->url)
                //     ->setData($jsonpayload)
                //     ->setHeaders($headers);


                // $request->send();

                // $response = $request->getData();
                // $this->log("json Response is: <pre>".print_r($response,true)."</pre>");
                // $this->log("array Response is: <pre>".print_r(json_decode($response,true),true)."</pre>");
                
                // if (null !== $response){
                //     $this->log('The Rules Engine responded correctly to the payload submission!');
                    
                //     $model->sent = 1;
                //     $model->save();
                //     break;
                // } else {
                //     $this->log('The response from the Rules Engine is null!');
                // }  
                // continue loop

            }else if ($model->sent == 1){
				$this->log('Payload already sent!');
				break;
			}else{
				$this->log('Payload already sent, but there was an unknown error!');
				break;
			}

			if ($try > $MAXtry){
				// imposto il sent to error
				$model->sent = 2;
				$model->save();
				break;
			}

            $this->log("Request id: $this->id, Status: " . $model->sent . ", Waiting seconds: " . $try . "\n");
            sleep($try);
            $try = $try * 2;
        }
        $this->log('Payload ' . $this->id . ' is not monitored anymore.');
        return ExitCode::OK;
    }


    private function test($headers, $body)
    {
        $client = new Client();
        // $headers = [
        //     'API-Key' => 'W0EngQISFmGwQNn-yH1OHQJR4dyn7ldSwkOA0kjHTqkBHsne',
        //     'API-Sign' => 'iEuxQFAmULsfDIgrv+yLZwNRZYsor0YhkSeX5ByviH6YnuXe30G/YafYKLTRgc6TI9BEpik67cWaF1t+QVVuoQ==',
        //     'x-fre-origin' => '1',
        //     'Authorization' => 'W0EngQISFmGwQNn-yH1OHQJR4dyn7ldSwkOA0kjHTqkBHsne',
        //     'Content-Type' => 'application/json'
        // ];


    //     $body = '{
    //   "event": {
    //     "plugin_name": "WooCommerce",
    //     "merchant_id": 1,
    //     "store_id": 1,
    //     "customer_id": 1,
    //     "client_address": "0xda9e2a20a018717d073a7acdfe93a099453b6844",
    //     "redirect_url": "https://api.fidelize.tk/index.php?r=v1",
    //     "total_price": 1,
    //     "items": [
    //       {
    //         "product_id": 16,
    //         "product_name": "iMac",
    //         "product_price": 12
    //       }
    //     ],
    //     "nonce": "1674073557832885"
    //   }
    // }';

        $this->log("headers are: <pre>" . print_r($headers, true) . "</pre>");
        $this->log("json body is: <pre>" . print_r($body, true) . "</pre>");
        // $this->log("body is: <pre>" . print_r($body, true) . "</pre>");
        // exit;

        $request = new Request('POST', 'https://rulesengine.fidelize.tk/api/v1/event', $headers, $body);
        $res = $client->sendAsync($request)->wait();
        echo $res->getBody();
        exit;
    }
}
