<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

use yii\helpers\Json;
use yii\helpers\Url;

use app\models\Transactions;
use app\models\Merchants;
use app\models\Stores;

use app\components\ApiLog;
use app\components\Settings;
use app\components\Messages;
use app\components\WebApp;


class V1Controller extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        // change this constant to true in PRODUCTION
        define('PRODUCTION',false);

        return [];
    }

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }



    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $log = new ApiLog;

        // Questa opzione abilita i wrapper URL per fopen (file_get_contents), in modo da potere accedere ad oggetti URL come file
		ini_set("allow_url_fopen", true);
		$raw_post_data = file_get_contents('php://input');

        if (false === $raw_post_data) $log->save('api.fidelity','index','manage event','Could not read from the php://input stream or invalid IPN received.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','php://input stream is valid.');

        if (false === $_POST) $log->save('api.fidelity','index','manage event','Could not read from the $_POST stream or invalid IPN received.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','$_POST stream is valid.');

        // fix POST messages
        $_POST = Json::decode($raw_post_data);

        if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Received _POST is:<pre>'.print_r($_POST,true).'</pre>');

        // VERIFICO CHE NEL POST CI SIA L'EVENT
        if (!isset($_POST['event'])) $log->save('api.fidelity','index','manage event','$_POST event is not valid.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','$_POST event is valid.');

        // now ipn is an object
        $ipn = json_decode(json_encode($_POST));

        if (true === empty($ipn)) $log->save('api.fidelity','index','manage event','Could not decode the JSON payload from Server.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Json payload and api keys are valid.');

        $payload = $ipn->event;
        if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Payload is: <pre>'.print_r($payload,true).'</pre>');

        if (true === empty($payload->store_id)) $log->save('api.fidelity','index','manage event','Invalid Server payment notification message received - did not receive store ID.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Store id is valid.');

        // if (true === empty($payload->customer_id)) $log->save('api.fidelity', 'index', 'manage event', 'Invalid Server payment notification message received - did not receive store ID.', true);
        // else if (!PRODUCTION) $log->save('api.fidelity', 'index', 'manage event', 'Store id is valid.');

        
        // check if actions exists
        if (false === isset($payload->actions)) $log->save('api.fidelity','index','manage event','Actions not set, cannot continue.',true);

        $actions = $payload->actions;

        if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Actions are: <pre>'.print_r($actions,true).'</pre>');

        $pay = null;

        foreach ($actions as $action => $fields) {
            // echo '<pre>'.print_r($action,true);
            // echo '<pre>'.print_r($fields,true);
            // echo '<pre>'.print_r($payload,true);

            switch ($action) {
                // pay customer
                case 'pay':
                    $pay = $this->payCustomer($payload, $fields);

                    if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Payment is: <pre>'.print_r($pay,true).'</pre>');
                    break;

            case 'mail':
              // send mail to customer
              break;
            case 'push':
              // send push message to customer
              break;


            }
        }

        $response = [
          'payCustomer' => $pay,
        ];

        $log->save('api.fidelity','index','manage event','Final event is: <pre>'.print_r($response,true).'</pre>');

        return $this->json($response);

    }



    /**
     * Sends Token. Save transaction and send notification
     * @param string $_POST['from'] the from ethereum address
     * @param string $_POST['to'] the to ethereum address
     * @param integer $_POST['amount'] the amount to be send
     * @return 'id_token' 'data' 'status' 'token_price' 'my_address'  'url'
     * @throws CJSON
     */

    private function payCustomer($payload, $fields)
    {
        $WebApp = new WebApp;
        $log = new ApiLog;

        $store_id = $payload->store_id;  // needed to select which blockchain use
        // $customer_id = $payload->customer_id;

        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','I\'m in.');
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','customer id is: <pre>'.print_r($payload->customer_id,true).'</pre>');
        if (!PRODUCTION) $log->save('api.fidelity', 'index', 'pay to customer', 'payload is: <pre>' . print_r($payload, true) . '</pre>');


        $store = Stores::findOne($store_id);
        $blockchain = $store->blockchain;
        // echo '<pre>'.print_r($blockchain,true);exit;
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','Blockchain is: <pre>'.print_r($blockchain,true).'</pre>');


        //Carico i parametri poa dello store
        $settings = Settings::poa($blockchain->id);
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','poa is: <pre>'.print_r($settings,true).'</pre>');


        // imposto eth
        $ERC20 = new Yii::$app->Erc20($blockchain->id);

        $fromAccount = $settings->sealer_address; //'0x654b98728213cf1e20e90b1942fdc5597984eb70'; // node1 fujitsu gabcoin
        $toAccount = $fields->client_address;
        $amount = $fields->token_amount;
        $memo = $fields->message;
        $decrypted = $WebApp->decrypt($settings->sealer_private_key);
        if (null === $decrypted){
			throw new NotFoundHttpException(404,'Cannot decrypt private key.');
		}
        $amountForContract = $amount * pow(10, $settings->decimals);

        // imposto il valore del nonce attuale
		$block = $ERC20->getBlockInfo();
		$nonce = $ERC20->getNonce($fromAccount);

		// genero la transazione nell'intervallo del nonce
		$maxNonce = $nonce + 10;

        while ($nonce < $maxNonce)
		{

            $data = [
                'nonce' => '0x'.dechex($nonce), //è un object BigInteger$nonce,
                'from' => $fromAccount, //indirizzo commerciante
                'contractAddress' => $settings->smart_contract_address, //indirizzo contratto
                'toAccount' => $toAccount,
                'amount' => $amountForContract,
                'gas' => '0x200b20', // $gas se supera l'importo 0x200b20 va in eerrore gas exceed limit !!!!!!
                'gasPrice' => '1000', // gasPrice giusto?
                'value' => '0',
                'chainId' => $settings->chain_id,
                'decryptedSign' => $decrypted,
            ];
            if (!PRODUCTION) $log->save('api.fidelity', 'index', 'pay customer', 'data is: <pre>' . print_r($data, true) . '</pre>');

			$tx = $ERC20->SendToken($data);

			if ($tx !== null){
				break;
			} else {
				$nonce++;
			}
		}

        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','tx is: <pre>'.print_r($tx,true).'</pre>');

		// echo '<pre>'.print_r($tx,true).'</pre>';
		// exit;
		if ($tx === null){
			throw new NotFoundHttpException(404,'Invalid nonce: '.$nonce);
		}

		//salva la transazione ERC20 in archivio
		$timestamp = time();
		$invoice_timestamp = $timestamp;

		//calcolo expiration time
		$totalseconds = $settings->invoice_expiration * 60; //poa_expiration è in minuti, * 60 lo trasforma in secondi
		$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

		//$rate = $this->getFiatRate(); // al momento il token è peggato 1/1 sull'euro
		$rate = 1; //eth::getFiatRate('token'); //

		$tokens = new Transactions;
		$tokens->id_user = $payload->customer_id;
		$tokens->status = 'new';
		$tokens->type = 'token';
		$tokens->token_price = $amount;
		$tokens->token_received = 0;
		$tokens->invoice_timestamp = $invoice_timestamp;
		$tokens->expiration_timestamp = $expiration_timestamp;
		$tokens->from_address = $fromAccount;
		$tokens->to_address = $toAccount;
		$tokens->blocknumber = $block->number;
		$tokens->txhash = $tx;
		$tokens->message = $memo;

		if (!($tokens->save())){
            if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','transactions errors are: <pre>'.print_r($tokens->errors,true).'</pre>');
			throw new NotFoundHttpException(404,print_r($tokens->errors));
		}

        // if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','transaction is: <pre>'.print_r($tokens,true).'</pre>');


		// notifica per chi  riceve i token (to_address)
		$notification = [
			'type' => 'token',
			'id_user' => $tokens->id_user,
			'status' => 'new',
			'description' => Yii::t('app','You received a new transaction.'),
			'url' => Url::to(["/tokens/view",'id'=>WebApp::encrypt($tokens->id)]),
			'timestamp' => time(),
			'price' => $tokens->token_price,
		];
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','customer notification is: <pre>'.print_r($notification,true).'</pre>');
		Messages::push($notification,'wallet');
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','customer notification sent');

        // notifica per chi  invia i token (alla dashboard)
        //$merchant = $store->merchant->user;
		$notification = [
			'type' => 'token',
			'id_user' =>  $store->merchant->user->id,
			'status' => 'new',
			'description' => Yii::t('app','You have sent a new transaction.'),
			'url' => Url::to(["/tokens/view",'id'=>WebApp::encrypt($tokens->id)]),
			'timestamp' => time(),
			'price' => $tokens->token_price,
		];
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','merchant notification is: <pre>'.print_r($notification,true).'</pre>');
		Messages::push($notification,'wallet');
        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','merchant notification sent');



        //adesso posso uscire CON IL JSON DA REGISTRARE NEL SW.
		$data = [
			'id' => $WebApp->encrypt($tokens->id), //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
			'status' => $tokens->status,
			'success' => true,
            'txhash' => $tx
		];

        if (!PRODUCTION) $log->save('api.fidelity','index','pay customer','return data is: <pre>'.print_r($data,true).'</pre>');


		return $this->json($data);
 	}

    private static function json ($data)	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}



}
