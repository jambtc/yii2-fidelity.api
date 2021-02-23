<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Logo');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.Notifi');
Yii::import('libs.NaPacks.Push');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.Utils.Utils');

Yii::import('libs.ethereum.eth');

require_once Yii::app()->params['libsPath'] . '/ethereum/web3/vendor/autoload.php';
require_once Yii::app()->params['libsPath'] . '/ethereum/ethereum-tx/vendor/autoload.php';
require_once Yii::app()->params['libsPath'] . '/ethereum/criptojs-aes.php';

use Web3\Web3;
use Web3\Contract;
use Web3p\EthereumTx\Transaction;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// magari api controller extend ERC20 controller, così le funzioni sono già
// tutte inserite in erc20 che basta solo ricpoiare e non bisogna modificare
// e/o aggoimgere nuovo codice ridondante .....



class ApiController extends Controller
{
  // define some variables
  public $decimals = 0; // token decimals
  public $transaction = null; // erc20 raw transaction
  public $count = 0; // nonce count

  private function setDecimals($decimals){
    $this->decimals = $decimals;
  }
  private function getDecimals(){
    return $this->decimals;
  }
  private function setNonce($count){
		$this->count = $count;
	}
	private function getNonce(){
		return $this->count;
	}
  private function setTransaction($transaction){
		$this->transaction = $transaction;
	}
	private function getTransaction(){
		return $this->transaction;
	}
  //recupera lo streaming json dal contenuto txt del body
	private function getJsonBody($response)
	{
		$start = strpos($response,'{',0);
		$substr = substr($response,$start);
		return json_decode($substr, true);
	}

  /* funzione per codificare il valore $value del tipo $type in hex */
	private function Encode(string $type, $value): string {
		 $len = preg_replace('/[^0-9]/', '', $type);

		 if (!$len) {
			 $len = null;
		 }

		 $type = preg_replace('/[^a-z]/', '', $type);
		 switch ($type) {
			 case "hash":
			 case "address":
				 if (substr($value, 0, 2) === "0x") {
					 $value = substr($value, 2);
				 }
				 break;
			 case "uint":
			 case "int":
				 //$value = BcMath::DecHex($value);
				 $value = dechex($value);
				 break;
			 case "bool":
				 $value = $value === true ? 1 : 0;
				 break;
			 case "string":
				 $value = self::Str2Hex($value);
				 break;
			 default:
				 echo 'Cannot encode value of type '. $type;
				 break;
		 }
		 return substr(str_pad(strval($value), 64, "0", STR_PAD_LEFT), 0, 64);
	 }

  public function init()
	{
    // change this constant to true in PRODUCTION
    define('PRODUCTION',false);
  }


	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array(
          'index', // action where receiving rules engine responses
        ),
				'users'=>array('*'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}
/**
 *
 * This function is able to manage post requests from Rules Engine
 *
 * It wanna manage PAY action, send mail action and push messages action after
 * it checked all is right.
 */

  public function actionIndex()
  {
    $save = new Save;

    if (rand(1,10)==1){
      $success = false;
      $message = 'Fake error!';
    }else{
      $success = true;
      $message = 'OK!';
    }
    // Questa opzione abilita i wrapper URL per fopen (file_get_contents), in modo da potere accedere ad oggetti URL come file
		ini_set("allow_url_fopen", true);
		$raw_post_data = file_get_contents('php://input');

    if (false === $raw_post_data) $save->WriteLog('dashboard','api','manage event','Could not read from the php://input stream or invalid IPN received.',true);
    else if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','php://input stream is valid.');

    if (false === $_POST) $save->WriteLog('dashboard','api','manage event','Could not read from the $_POST stream or invalid IPN received.',true);
    else if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','$_POST stream is valid.');

    // fix POST messages
    $_POST = CJSON::decode($raw_post_data);

    if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Received _POST is:<pre>'.print_r($_POST,true).'</pre>');

    // VERIFICO CHE NEL POST CI SIA L'EVENT
    if (!isset($_POST['event'])) $save->WriteLog('api','api','manage event','$_POST event is not valid.',true);
    else if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','$_POST event is valid.');

    // VERIFICO CHE I DATI INVIATI SIANO CORRETTI

          // al momento non avnedo posibilità di inviare api-keys salto questo controllo
          // Yii::import('ext.APIKeys');
          // $ipn = (object) APIKeys::check($_POST);

          // now ipn is an object
          $ipn = json_decode(json_encode($_POST));


    if (true === empty($ipn)) $save->WriteLog('dashboard','api','manage event','Could not decode the JSON payload from Server.',true);
    else if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Json payload and api keys are valid.');

    $payload = $ipn->event;
    if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Payload is: <pre>'.print_r($payload,true).'</pre>');

		if (true === empty($payload->id)) $save->WriteLog('dashboard','api','manage event','Invalid Server payment notification message received - did not receive invoice ID.',true);
		else if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Ipn id is valid.');

    // check if actions exists
    if (false === isset($payload->actions)) $save->WriteLog('dashboard','api','manage event','Actions not set, cannot continue.',true);

    $actions = $payload->actions;

    if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Actions are: <pre>'.print_r($actions,true).'</pre>');

    $pay = null;

    foreach ($actions as $action => $fields) {
      switch ($action) {
        case 'pay':
          // pay customer
          $pay = $this->payCustomer($payload->customer_id,$fields);
          if (!PRODUCTION) $save->WriteLog('dashboard','api','manage event','Payment is: <pre>'.print_r($pay,true).'</pre>');
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
      '_post'=>$_POST,
      'headers'=>getallheaders(),
      'success'=>$success,
      'message'=>$message,
      'payCustomer' => $pay,
    ];

    $save->WriteLog('dashboard','api','manage event','Final event is: <pre>'.print_r($response,true).'</pre>');

    echo CJSON::encode($response);
  }



  /**
   * Sends Token. Save transaction and send notification
   * @param string $_POST['from'] the from ethereum address
   * @param string $_POST['to'] the to ethereum address
   * @param integer $_POST['amount'] the amount to be send
   * @return 'id_token' 'data' 'status' 'token_price' 'my_address'  'url'
   * @throws CJSON
   */

  private function payCustomer($customer_id, $action) {
    $save = new Save;

    //Carico i parametri dell'account principale
		$settings=Settings::load();
		if ($settings === null
			|| empty($settings->poa_sealerAccount)
			|| empty($settings->poa_sealerPrvKey)
		){
      $save->WriteLog('dashboard','api','manage event','Sealer account data cannot be found.',true);
		}

    $fromAccount = $settings->poa_sealerAccount; //'0x654b98728213cf1e20e90b1942fdc5597984eb70'; // node1 fujitsu gabcoin
    $toAccount = $action->client_address;
    $amount = $action->token_amount;
    $memo = $action->message;

    // start to create transaction
  	$pow = 0.00021 * pow(10,10);
  	$hex = dechex($pow);
  	$gas = '0x'.$hex;

    $prv_key = crypt::Decrypt($settings->poa_sealerPrvKey);
  	if (null === $prv_key) $save->WriteLog('dashboard','api','manage event','Sealer private key empty.',true);

    // imposto i decimali del token
    $this->setDecimals($settings->poa_decimals);
  	$amountForContract = $amount * pow(10, $this->getDecimals());

		// CREATE transaction
		/**
		  * This is fairly straightforward as per the ABI spec
		  * First you need the function selector for test(address,uint256) which is the first four bytes of the keccak-256 hash of that string, namely 0xba14d606.
		  * Then you need the address as a 32-byte word: 0x000000000000000000000000c5622be5861b7200cbace14e28b98c4ab77bd9b4.
		  * Finally you need amount (10000) as a 32-byte word: 0x0000000000000000000000000000000000000000000000000000000000002710
			*	0x03746bfdeacebf4f37e099511c16683df3bac8eb																										 0000000000000000000000000000000000000000000000000000000000000079
		*/

  	$data_tx = [
  		'selector' => '0xa9059cbb', //ERC20	0xa9059cbb function transfer(address,uint256)
  		'address' => self::Encode("address", $toAccount), // $receiving_address è l'indirizzo destinatario,
  		'amount' => self::Encode("uint", $amountForContract), //$amount l'ammontare della transazione (da moltiplicare per 10^2)
  	];

  	// recupero la nonce per l'account
  	$nonce = 0;
  	$poaNode = WebApp::getPoaNode();

    if (!$poaNode){
  		$save->WriteLog('bolt','walletERC20','send',"All Nodes are down.",true);
		}else{
			$web3 = new Web3($poaNode);
		}

  	// echo '<pre>data_tx: '.print_r($data_tx,true).'</pre>';

  	$web3->eth->getTransactionCount($fromAccount, function ($err, $res) use (&$nonce) {
  		if($err !== null) {
        $save->WriteLog('bolt','walletERC20','send',$err->getMessage(),true);
  			}
  			$nonce = $res;
  		});

  		// echo '<pre>[ricerca nonce] '.print_r('0x'.dechex(gmp_intval($nonce->value)),true).'</pre>';
  		// exit;

  		self::setNonce(gmp_intval($nonce->value));

  		while (self::getNonce() < 1000)
  		{
  			$transaction = new Transaction([
  			  'nonce' => '0x'.dechex(self::getNonce()), //è un object BigInteger
  				'from' => $fromAccount, //indirizzo commerciante
  				'to' => $settings->poa_contractAddress, //indirizzo contratto
  				'gas' => '0x200b20', // $gas se supera l'importo 0x200b20 va in eerrore gas exceed limit !!!!!!
  				'gasPrice' => '1000', // gasPrice giusto?
  				'value' => '0',
  				'chainId' => $settings->poa_chainId,
  				'data' =>  $data_tx['selector'] . $data_tx['address'] . $data_tx['amount'],
  			]);

  			$transaction->offsetSet('chainId', $settings->poa_chainId);
  			// echo '<pre>Transazione: '.print_r($transaction,true).'</pre>';
  			// exit;

  			$signed_transaction = $transaction->sign($prv_key); // la chiave derivata da json js AES to PHP
  			// echo '<pre>Transazione firmata: '.print_r($signed_transaction,true).'</pre>';
  			// exit;

  			$web3->eth->sendRawTransaction(sprintf('0x%s', $signed_transaction), function ($err, $tx) {
  				if ($err !== null) {
  					$jsonBody = $this->getJsonBody($err->getMessage());

  					// echo '<pre>[response] '.var_dump($jsonBody,true).'</pre>';
  					// exit;
  					if ($jsonBody === NULL){
  						$count = self::getNonce() +1;
  						self::setNonce($count);
  					}else{
              $save->WriteLog('bolt','walletERC20','send',$jsonBody['error']['message'],true);
  					}
  				}
  				// echo 'TX: ' . $tx;
  				// exit;
  				self::setTransaction($tx);

  			});
  			if (self::getTransaction() !== null){
  				break;
  			}
  		}

  		// echo '<pre>ERRORE: [get nonce] '.print_r(self::getNonce(),true).'</pre>';
  		// exit;
  		//
  		if (self::getTransaction() === null)
        $save->WriteLog('bolt','walletERC20','send','Invalid nonce: '.self::getNonce(),true);

  		// blocco in cui presumibilmente avviene la transazione
  		$response = null;
  		$web3->eth->getBlockByNumber('latest',false, function ($err, $block) use (&$response){
  			if ($err !== null) {
  				throw new CHttpException(404,'Errore: '.$err->getMessage());
  			}
  			$response = $block;
  		});

  		//salva la transazione ERC20
  	 	$timestamp = time();
  	 	$invoice_timestamp = $timestamp;

  	 	//calcolo expiration time
  	 	$totalseconds = $settings->poa_expiration * 60; //poa_expiration è in minuti, * 60 lo trasforma in secondi
  	 	$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

  	 	//$rate = $this->getFiatRate(); // al momento il token è peggato 1/1 sull'euro
  		$rate = eth::getFiatRate('token'); //

	 		$attributes = array(
	 			'id_user' => $customer_id,
	 			'status'	=> 'new',
				'type'	=> 'token',
	 			'token_price'	=> $amount,
	 			'token_ricevuti'	=> 0,
	 			'fiat_price'		=> abs($rate * $amount),
	 			'currency'	=> 'EUR',
	 			'item_desc' => 'wallet',
	 			'item_code' => '0',
	 			'invoice_timestamp' => $invoice_timestamp,
	 			'expiration_timestamp' => $expiration_timestamp,
	 			'rate' => $rate,
	 			'from_address' => $fromAccount,
				'to_address' => $toAccount,
				'blocknumber' => hexdec($response->number), // numero del blocco in base 10
				'txhash'	=> self::getTransaction(),
	 		);
  		//salvo la transazione in db. Restituisce object
  		$tokens = $save->Token($attributes);

  		// salvo l'eventuale messaggio inserito
  		if (!empty($memo)){
				$message = $save->Memo([
					'id_token'=>$tokens->id_token,
					'memo'=>crypt::Encrypt($memo)
				]);
			}

	 		//salva la notifica
	 		$notification = array(
	 			'type_notification' => 'token',
	 			'id_user' => $tokens->id_user,
	 			'id_tocheck' => $tokens->id_token,
	 			'status' => 'new',
				'description' => 'You received a new transaction.',
				'url' => Yii::app()->createUrl("tokens/view",['id'=>crypt::Encrypt($tokens->id_token)]),
	 			'timestamp' => $timestamp,
	 			'price' => $rate * $amount,
	 			'deleted' => 0,
	 		);

      Push::Send($save->Notification($notification),'bolt');

  		//eseguo lo script che si occuperà in background di verificare lo stato dell'invoice appena creata...
  		$cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic send --id='.crypt::Encrypt($tokens->id_token);
  		Utils::execInBackground($cmd);

  	 	//adesso posso uscire
  	 	$send_json = array(
  			'id' => $invoice_timestamp, //NECESSARIO PER IL SALVATAGGIO IN  indexedDB quando ritorna al Service Worker
  	 		'id_token' => crypt::Encrypt($tokens->id_token),
  	 		'data'	=> WebApp::dateLN($invoice_timestamp,$tokens->id_token),
  	 		'status' => WebApp::walletIconStatus($tokens->status),
  			'token_price' => WebApp::typePrice($tokens->token_price,'sent'),
				'from_address' => $fromAccount,
				'to_address' => $toAccount,
	 			'url' => Yii::app()->createUrl("tokens/view",['id'=>crypt::Encrypt($tokens->id_token)]),
	 		);

     	return $send_json;
  }








}
