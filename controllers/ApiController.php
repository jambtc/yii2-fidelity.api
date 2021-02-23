<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

use yii\helpers\Json;
use yii\helpers\Url;

use app\models\BoltTokens;

use app\components\ApiLog;
use app\components\Settings;
use app\components\Messages;
use app\components\WebApp;


class ApiController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        // change this constant to true in PRODUCTION
        define('PRODUCTION',false);

        return [
            // 'access' => [
            //     'class' => AccessControl::className(),
            //     'only' => ['logout'],
            //     'rules' => [
            //         [
            //             'actions' => ['logout'],
            //             'allow' => true,
            //             'roles' => ['@'],
            //         ],
            //     ],
            // ],

            // 'verbs' => [
            //     'class' => VerbFilter::className(),
            //     'actions' => [
            //         'index' => ['post'],
            //     ],
            // ],
        ];
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

        if (true === empty($payload->id)) $log->save('api.fidelity','index','manage event','Invalid Server payment notification message received - did not receive invoice ID.',true);
        else if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Ipn id is valid.');

        // check if actions exists
        if (false === isset($payload->actions)) $log->save('api.fidelity','index','manage event','Actions not set, cannot continue.',true);

        $actions = $payload->actions;

        if (!PRODUCTION) $log->save('api.fidelity','index','manage event','Actions are: <pre>'.print_r($actions,true).'</pre>');

        $pay = null;

        foreach ($actions as $action => $fields) {
          switch ($action) {
            case 'pay':
              // pay customer
              $pay = $this->payCustomer($payload->customer_id,$fields);
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

    private function payCustomer($customer_id, $action)
    {
        $WebApp = new WebApp;
        $log = new ApiLog;

        //Carico i parametri dell'account principale
        $settings = Settings::load();
  		if ($settings === null
  			|| empty($settings->poa_sealerAccount)
  			|| empty($settings->poa_sealerPrvKey)
  		){
            $log->save('api.fidelity','payCustomer','manage event','Sealer account data cannot be found.',true);
  		}

        $fromAccount = $settings->poa_sealerAccount; //'0x654b98728213cf1e20e90b1942fdc5597984eb70'; // node1 fujitsu gabcoin
        $toAccount = $action->client_address;
        $amount = $action->token_amount;
        $memo = $action->message;
        $decrypted = $WebApp->decrypt($settings->poa_sealerPrvKey);
        if (null === $decrypted){
			throw new HttpException(404,'Cannot decrypt private key.');
		}
        $amountForContract = $amount * pow(10, $settings->poa_decimals);

        // imposto il valore del nonce attuale
		$block = Yii::$app->Erc20->getBlockInfo();
		$nonce = Yii::$app->Erc20->getNonce($fromAccount);

		// genero la transazione nell'intervallo del nonce
		$maxNonce = $nonce + 10;

        while ($nonce < $maxNonce)
		{
			$tx = Yii::$app->Erc20->SendToken([
				'nonce' => $nonce,
				'from' => $fromAccount, //indirizzo commerciante
				'contractAddress' => $settings->poa_contractAddress, //indirizzo contratto
				'toAccount' => $toAccount,
				'amount' => $amountForContract,
				'gas' => '0x200b20', // $gas se supera l'importo 0x200b20 va in eerrore gas exceed limit !!!!!!
				'gasPrice' => '1000', // gasPrice giusto?
				'value' => '0',
				'chainId' => $settings->poa_chainId,
				'decryptedSign' => $decrypted,
			]);

			if ($tx !== null){
				break;
			} else {
				$nonce++;
			}
		}
		// echo '<pre>'.print_r($tx,true).'</pre>';
		// exit;
		if ($tx === null){
			throw new HttpException(404,'Invalid nonce: '.$nonce);
		}

		//salva la transazione ERC20 in archivio
		$timestamp = time();
		$invoice_timestamp = $timestamp;

		//calcolo expiration time
		$totalseconds = $settings->poa_expiration * 60; //poa_expiration è in minuti, * 60 lo trasforma in secondi
		$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

		//$rate = $this->getFiatRate(); // al momento il token è peggato 1/1 sull'euro
		$rate = 1; //eth::getFiatRate('token'); //

		$tokens = new BoltTokens;
		$tokens->id_user = $customer_id;
		$tokens->status = 'new';
		$tokens->type = 'token';
		$tokens->token_price = $amount;
		$tokens->token_ricevuti = 0;
		$tokens->fiat_price = abs($rate * $amount);
		$tokens->currency = 'EUR';
		$tokens->item_desc = 'wallet';
		$tokens->item_code = '0';
		$tokens->invoice_timestamp = $invoice_timestamp;
		$tokens->expiration_timestamp = $expiration_timestamp;
		$tokens->rate = $rate;
		$tokens->from_address = $fromAccount;
		$tokens->to_address = $toAccount;
		$tokens->blocknumber = hexdec($block->number);
		$tokens->txhash = $tx;
		$tokens->memo = $memo;

		if (!($tokens->save())){
			throw new HttpException(404,print_r($tokens->errors));
		}

		// notifica per chi ha inviato (from_address)
		$notification = [
			'type_notification' => 'token',
			'id_user' => $tokens->id_user,
			'id_tocheck' => $tokens->id_token,
			'status' => 'new',
			'description' => Yii::t('app','You received a new transaction.'),
			'url' => Url::to(["/tokens/view",'id'=>WebApp::encrypt($tokens->id_token)]),
			'timestamp' => time(),
			'price' => $tokens->token_price,
			'deleted' => 0,
		];
		Messages::push($notification);

		//adesso posso uscire CON IL JSON DA REGISTRARE NEL SW.
		$data = [
			'id' => $WebApp->encrypt($tokens->id_token), //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
			'status' => $tokens->status,
			'success' => true,
            'txhash' => $tx
		];

		return $this->json($data);
 	}

    private static function json ($data)	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}



}
