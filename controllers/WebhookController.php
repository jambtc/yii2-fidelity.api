<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

use yii\helpers\Json;
use yii\helpers\Url;

use app\components\ApiLog;
use app\components\Settings;
use app\components\Messages;
use app\components\WebApp;
use app\components\Seclib;

use app\models\Users;
use app\models\MPWallets;
use app\models\Stores;
use app\models\ReRequests;
use app\models\ApiKeys;
use app\models\Merchants;


class WebhookController extends Controller
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        // change this constant to true in PRODUCTION
        define('PRODUCTION', false);

        // urls where rules engine have to return its response
        define('PRODUCTION_REDIRECT_URL', 'https://dashboard.txlab.it/index.php?r=api');
        define('SANDBOX_REDIRECT_URL', 'https://api.fidelize.tk/v1');

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
    public function actionWoocommerce()
    {
        $log = new ApiLog;
        $WebApp = new WebApp;

        $request = Yii::$app->request;

        $get = $request->get();
        $post = $request->post();
        $headers = Yii::$app->request->headers;
        $rawcontent = file_get_contents('php://input');

        // check if post payload exists
        if (false === $post) $log->save('api','webhook','woocommerce','Could not read from the $_POST stream or invalid IPN received.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','$_POST stream is valid.');

        // check if id exists
        if (true === !empty($post['webhook'])){
            $log->save('api','webhook','woocommerce','Webhook update registered.');
            echo Json::encode([
                'success' => true,
            ]);
            exit;
        }

        // check if id exists
        if (true === empty($post['id'])) $log->save('api','webhook','woocommerce','Invalid Server payment notification message received - did not receive invoice ID.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Ipn id is valid.');

        // check if status exists
        if (false === isset($post['status'])) $log->save('api','webhook','woocommerce','Status order not set, cannot continue.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Status order is: '.$post['status']);

        // if status <> 'completed' exit
        if ($post['status'] != 'completed') $log->save('api','webhook','woocommerce','Status order is not completed. Its status is: '.$post['status'],true);

        // check if store id exist
        if (false === $get['storeid']) $log->save('api','webhook','woocommerce','Store id isn\'t set or invalid.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Store id is: '.$get['storeid']);

        // check if store exist
        $store = Stores::find()
 	     	->andWhere(['id_store'=>$WebApp->decrypt($get['storeid'])])
 	    	->one();

        if (null === $store) $log->save('api','webhook','woocommerce','Store account not found.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Store account found: id # '.$store->id_store);

        // check if pkey exist
        if (false === $get['pkey']) $log->save('api','webhook','woocommerce','Public key isn\'t set or invalid.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Public key is: '.$get['pkey']);

        // check if public api key exists
        $apikeys = ApiKeys::find()
            ->andWhere(['key_public'=>$get['pkey']])
            ->one();

        if (null === $apikeys) $log->save('api','webhook','woocommerce','Api keys not found.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Api keys found. Public key is: '.$apikeys->key_public);

        // now I'm ready to check signature
        $secret = $WebApp->decrypt($apikeys->key_secret);
        $receivedHash = '';
        foreach ($headers as $name => $value) {
            if (strtoupper($name) == 'X-WC-WEBHOOK-SIGNATURE'){
                $receivedHash = $value[0];
            }
        }

        // IN FASE DI TEST È DISABILITATO IL CONTROLLO
        $generatedHash = base64_encode(hash_hmac('sha256', $rawcontent, $secret, true));
        if (PRODUCTION && $receivedHash !== $generatedHash){
            $log->save('api','webhook','woocommerce','Signature or api keys are invalid.',true);
        } else if (!PRODUCTION && $receivedHash !== $generatedHash) {
            $log->save('api','webhook','woocommerce',
            'Signature is invalid, but the program will not be stopped. <pre>'.print_r(
                [
                    'receivedHash'=>$receivedHash,
                    'generatedHash'=>$generatedHash,
                    'secret' => $secret,
                ],true).'</pre>');

        } else if (!PRODUCTION) {
            $log->save('api','webhook','woocommerce','Signature is valid.');
        }

        // NOW, WE CAN SEARCH FOR CUSTOMER DATA ONLY BY HIS EMAIL
        $customer = Users::find()
 	     	->andWhere(['email'=>$post['billing']['email']])
 	    	->one();

        if (null === $customer) $log->save('api','webhook','woocommerce','Customer account not found.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Customer account found: id # '.$customer->id);

        // cerco il wallet address
        $customerWalletAddress = MPWallets::find()->userAddress($customer->id);
        if (null === $customerWalletAddress) $log->save('api','webhook','woocommerce','Customer wallet address account not found.',true);
        else if (!PRODUCTION) $log->save('api','webhook','woocommerce','Customer wallet address found: '.$customerWalletAddress);

        // generate the mandatory elements
        $mandatory = new \stdClass;
        $mandatory->plugin_name = 'WooCommerce';
        $mandatory->merchant_id = $store->id_merchant;
        $mandatory->customer_id = $customer->id;
        $mandatory->client_address = $customerWalletAddress;
        $mandatory->redirect_url = (PRODUCTION) ? PRODUCTION_REDIRECT_URL : SANDBOX_REDIRECT_URL ;
        $mandatory->total_price = $post['total'] * 1;

        // generate the items
        $items = null;
        foreach ($post['line_items'] as $id => $product){
            $items[$id]['product_id'] = $product['product_id'];
            $items[$id]['product_name'] = $product['name'];
            $items[$id]['product_price'] = $product['price'] * 1;
        }
        $mandatory->items = $items;

        // generating the final payload
        $payload = new \stdClass;
        $payload->event = $mandatory;
        if (!PRODUCTION) $log->save('api','webhook','woocommerce','New Payload to Rules Engine Server is: <pre>'.print_r($payload,true).'</pre>');

        // save payload in archive
        $model = new ReRequests;
        $model->timestamp = time();
        $model->id_merchant = $mandatory->merchant_id;
        $model->payload = json_encode($payload);
        $model->sent = 0; // NON INVIATO
        $model->save();
        if (!PRODUCTION) $log->save('api','webhook','woocommerce','New Payload saved');

        //eseguo lo script che si occuperà in background di verificare lo stato dell'evento appena creata...
        $cmd = Yii::$app->basePath.DIRECTORY_SEPARATOR.'yii request --id='.$WebApp->encrypt($model->id_request);
        $ssh = Seclib::execInBackground($cmd);

        //
        $response = [
            'request_id' => $WebApp->encrypt($model->id_request),
            'code' => 200,
        ];
        $log->save('api','webhook','woocommerce','Final response is: <pre>'.print_r($response,true).'</pre>');

        // Respond with HTTP 200
        return \Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => $response
        ]);
        // Yii::$app->response->statusCode = 200;
		// header("HTTP/1.1 200 OK");
    }





    private static function json ($data)	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}



}
