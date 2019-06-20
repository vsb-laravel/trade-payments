<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use cryptofx\payments\Payment;

class Paysol extends Payment{
    protected $name = 'paysol';

    protected $_gateUrl = 'https://api.paysol.com/v2';
    protected $_settings = [
        'successurl' => '',
        'failureurl' => '',
        'postbackurl' => '',

        'user' => 'forex2crypto',
        'password' => 'yI91x-vbaDE-1MLKw-3XXCt',
        'ProfileID' => '16870',

        'view'=>'app.form.paysol'
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(!is_null($merchant)){
            $this->_gateUrl = isset($merchant->settings['gate'])?$merchant->settings['gate']:$this->_gateUrl;
            $this->_settings['user'] = isset($merchant->settings['user'])?$merchant->settings['user']:$this->_settings['user'];
            $this->_settings['password'] = isset($merchant->settings['password'])?$merchant->settings['password']:$this->_settings['password'];
            $this->_settings['ProfileID'] = isset($merchant->settings['ProfileID'])?$merchant->settings['ProfileID']:$this->_settings['ProfileID'];
            $this->_settings['view'] = isset($merchant->settings['view'])?$merchant->settings['view']:$this->_settings['view'];
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
    }
    public function response($resp){
        $response = isset($resp["response"])?$resp["response"]:['ResponseCode'=>0,'ResponseMessage'=>'No response get'];
        $success = ($response!==false) & (intval($response['ResponseCode']) == 1);
        $ret = [
            'success' => $success,
            'description' =>  $response['ResponseMessage'],
            'method' => $resp['transaction']['PaymentType'],
            'reference' => $resp['transaction']['CustomerTransID'],
            'orderId' => $resp['transaction']['CustomerTransID'],
            'amount' => $resp['transaction']['Amount'],
            'currency' => $resp['transaction']['Currency'],
            'fee' => 0,
            'answer' => $resp
        ];
        return new Response($ret);
    }
    public function build($args){
        // checks
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        $amount = floatval($args['amount']);
        $stage = isset($args['stage'])?$args['stage']:false;
        $user = $args['user'];
        $res = [];
        if($stage){
            $transType = isset($args['TransType'])?$args['TransType']:'CT'; //String 2 Y “CT” – Credit,	“DT” - Debit
            $res = [
                "authenticate" => [
                    'user' => $this->_settings['user'],
                    'password' => $this->_settings['password']
                ],
                "transaction" => [
                    'ProfileID'  => $this->_settings['ProfileID'], //String 1-50 Y
                    'CustomerTransID'  => isset($args['order_id'])?$args['order_id']:'', //String 1-50 Y
                    'CustomerID'  => $user->id,
                    'FirstName'  => isset($args['FirstName'])?$args['FirstName']:$user->name, //String 1-50 Y
                    'LastName'  => isset($args['LastName'])?$args['LastName']:$user->surname, //String 1-50 Y
                    'Country'  => isset($args['Country'])?$args['Country']:$user->country, //String 2 Y
                    'CheckHolder'  => isset($args['CheckHolder'])?$args['CheckHolder']:$user->title, //String 1-100 Y
                    'Address'  => isset($args['Address'])?$args['Address']:$user->address->address1, //String 1-50 Y
                    'City'  => isset($args['City'])?$args['City']:$user->address->city, //String 1-50 Y
                    'State'  => isset($args['State'])?$args['State']:'', //String 2 Y
                    'PostalCode'  => isset($args['PostalCode'])?$args['PostalCode']:$user->address->zip, //String 1-50 Y
                    'Phone'  => isset($args['Phone'])?$args['Phone']:$user->phone, //String 1-50 Y
                    'Email'  => isset($args['Email'])?$args['Email']:$user->email, //String 1-50 Y
                    'Amount' => $amount,
                    'BankName'  => isset($args['BankName'])?$args['BankName']:'', //String 1-100 Y
                    'RoutingNumber'  => isset($args['RoutingNumber'])?$args['RoutingNumber']:'', //String 9 Y
                    'AccountNumber'  => isset($args['AccountNumber'])?$args['AccountNumber']:'', //String 4-20 Y
                    'AccountType'  => isset($args['AccountType'])?$args['AccountType']:'', //String 1 Y C	– Checking,	S	- Savings
                    'Currency'  => isset($args['Currency'])?$args['Currency']:'USD', //String 3 Y
                    'TransType'  => $transType,
                    'PaymentType'  => isset($args['PaymentType'])?$args['PaymentType']:'', //String 3 N “ICL”	,“ACH”,	“CKP”, “C21”,”EFT”
                ]
            ];
            Log::debug('PG:Paysol request:'.json_encode($res));
            $request = new Request($res);
            $response = $request->post($this->_gateUrl);
            Log::debug('PG:Paysol response:'.$response);
            $resp = array_merge($res,["response"=>json_decode($response,true)]);
            $callback = new Request($resp);
            $callback->post($this->_settings['postbackurl']);
            return $this->response($resp);
        }
        else {
            $res['merchant_id'] = Merchant::where('name',$this->name)->first()->id;
            $res['user'] = $user;
            $res['amount'] = $amount;
            $res['Currency'] = 'USD';
            $res['stage'] = "1";
        }
        return $res;
    }
    public function sale($args,$format='form'){ //overide sale method
        $res = $this->build($args);
        if ( $res instanceof Response){
            $url = ($res->success)?$this->_settings['successurl']:$this->_settings['failureurl'];
            return [
                    "redirect"=>[
                        "form"=>"<form action='{$url}' mathod='GET'></form>"
                    ]
                ];
        }
        return [
            "append"=>[
                "view"=>view($this->_settings['view'],["page"=>$res])->render()
            ]
        ];

    }

};
?>
