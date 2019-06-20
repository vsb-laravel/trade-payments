<?php namespace Vsb\payments\gateway;

use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

class MasterPay
{
    protected $name = 'masterPay';
    // protected $_gateUrl = 'https://secure.fxpaymentservices.com/payment/auth';
    protected $idHash = 'c0e83190-1c23-4d37-977f-e225d37647e9';
    protected $accountIdHash = '48c2ce49-40e7-4681-b603-242288e74901';
    protected $_gateUrl = 'https://securemasterpay.com/FE/rest/tx/purchase/w/execute';
    protected $_settings = [
        'merchantIdHash' => '',
        'merchantAccountIdHash' => '',
        'encryptedAccountUsername' => 'F2cprob25aae1e8f92',
        'encryptedAccountPassword' => 'ecbe68461bab454dad741283011dccaf',
        'lang' => 'en',
        'transactionInfo' => [
            'apiVersion' => '1.1.0',
            'requestId' => '79c57c396fd23841', //я хз что это и откуда брать
            'txSource' => '1',
            'orderData' => [
                'orderId' => '',//уникальный id для каждого платежка(т.е. авторизация или покупка) для торгового счета
                'amount' => '',
                'currencyCode' => '',
            ],
            'billingAddress' => [
                'firstName' => '',
                'lastName' => '',
                'phone' => '',
                'email' => '',
                'countryCode' => ''
            ]
        ],
        'cancelUrl' => '',
        'returnUrl' => '',
        'signature' => '',
        'key'=>''
    ];
    protected $_responseDom=false;
    protected $_response='';

    public function __construct($a=[]){//, $idHash, $accountIdHash){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $key = $merchant->settings['key'];
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(!is_null($merchant)){
            $this->_gateUrl = $merchant->settings['gate'];
            $this->_settings['key'] = isset($merchant->settings['key'])?$merchant->settings['key']:$this->_settings['key'];
            $this->_settings['merchantIdHash'] = $this->hashDataInBase64($merchant->settings['merchant_id']);
            $this->_settings['merchantAccountIdHash'] = $this->hashDataInBase64($merchant->settings['merchant_acc_id']);
            $this->_settings['encryptedAccountUsername'] = $this->encryptDataInBase64($merchant->settings['user_name'], $key);
            $this->_settings['encryptedAccountPassword'] = $this->encryptDataInBase64($merchant->settings['password'], $key);
            $this->_settings['lang'] = isset($merchant->settings['language'])?$merchant->settings['language']:'en';
            $this->_settings['host'] = isset($merchant->settings['host'])?$merchant->settings['host']:$host;
            $this->_settings['method'] = isset($merchant->settings['method'])?$merchant->settings['method']:'CreditCard';
            $this->_settings['transactionInfo']['orderData']['currencyCode'] = isset($merchant->settings['currency'])?$merchant->settings['currency']:'USD';
            $host = $this->_settings['host'];
            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }
        $this->_settings["cancelUrl"]=$host.'/pay/'.$this->name.'/error.jsp';
        $this->_settings["returnUrl"]=$host.'/pay/'.$this->name.'/return.jsp';
        $this->_settings["notificationUrl"]=$host.'/pay/'.$this->name.'/notificaton.jsp';
    }

    public function success($resp){}

    public function response($resp){
        $ret = [
            'success' => (isset($resp['approval_code'])),
            'description' => isset($resp['description'])?$resp['description']:'',
            'reference' => isset($resp['id'])?$resp['id']:'-',
            'orderId' => isset($resp['order'])?$resp['order']:'0',
            'amount' => isset($resp['amount'])?$resp['amount']:'0',
            'currency' => isset($resp['currency'])?$resp['currency']:'0',
            'fee' => '0',
        ];

        return new Response($ret);
    }

    public function fail(){}

    public function back(){}

    public function sale($args,$format='form'){
        $request = $this->build($args);
        $response = [];
        if($format == 'array'){
            $response=[
                'redirect'=>[
                    'url'=>$this->_gateUrl,
                    'method'=>'POST'
                ],
                'fields'=>$request->toArray()
            ];
        }
        else {
            $form = '<form action="'.$this->_gateUrl.'" method="POST">'.$request->toForm().'</form>';
            Log::debug("MasterPay form:".$form);
            $response = [
                "redirect"=>[
                    "form"=>$form
                ]
            ];
        }
        return $response;
    }

    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $amount = number_format(floatval($args['amount']),2,'.','');
        $user = $args['user'];
        $orderData = [
            'orderId' => $this->_settings["transactionInfo"]['orderData']['orderId'],
            'orderDescription'=>'Deposit #'.$user->id,
            'amount'=>$amount,
            'currencyCode'=>'USD'
        ];
        $billingAddress = [
            "firstName" =>$user->name,
            "lastName" =>$user->surname,
            "email" =>$user->email,
            "phone" =>$user->phone,
            "countryCode" =>$user->country
        ];
        $transactionInfo = [
            'apiVersion' => $this->_settings["transactionInfo"]['apiVersion'],
            'requestId' => $this->_settings["transactionInfo"]['requestId'],
            'txSource' => $this->_settings["transactionInfo"]['txSource'],
            'orderData' => $orderData,
            'billingAddress'=>$billingAddress,
        ];
        $arg = [
            'merchantIdHash' => $this->_settings["merchantIdHash"],
            'merchantAccountIdHash' => $this->_settings["merchantAccountIdHash"],
            'encryptedAccountUsername' => $this->_settings["encryptedAccountUsername"],
            'encryptedAccountPassword' => $this->_settings["encryptedAccountPassword"],
            'lang' => $this->_settings["lang"],
            'transactionInfo' => $transactionInfo,
            'payment' => $this->_settings["method"],
            'order' => $args['order_id'],
//            'url'=>$this->_settings["successurl"],
            'cancelUrl'=>$this->_settings["cancelUrl"],
            'returnUrl'=>$this->_settings["returnUrl"],
        ];
        // $arg['signature'] = $this->createSignature($arg);
        return new Request([
            "request"=>$this->encryptDataInBase64($arg)
        ]);
    }

    function createSignature($input,$key=false)
    {
        return $this->encryptDataInBase64($input,$this->_settings['key']);
        // $alg = MCRYPT_RIJNDAEL_128; // AES
        // $mode = MCRYPT_MODE_ECB; // ECB
        // $block_size = mcrypt_get_block_size($alg, $mode);
        // $pad = $block_size - (strlen($input) % $block_size);
        // $input .= str_repeat(chr($pad), $pad);
        // $crypttext = mcrypt_encrypt($alg,$key, $input , $mode);
        // $output = hash('sha256',$crypttext,true);


        // $cipher = 'AES-256-CBC';//'aes-256-cbc';
        // $key = md5($this->_settings['m_key_addition'].$args['m_orderid']);
        // $ivlen = openssl_cipher_iv_length($cipher);
        // $iv = openssl_random_pseudo_bytes($ivlen);
        // Log::debug('iv:'.$iv);
        // $m_params = urlencode(base64_encode(openssl_encrypt(json_encode($arParams), $cipher, $key, OPENSSL_RAW_DATA,$iv)));

        // $signature = trim(base64_encode($output));
        // return $signature ;
    }


    public function encryptDataInBase64($input, $key=false)
    {
        Log::debug('MasterPay encrypting request by key ['.$this->_settings['key'].'] '.json_encode($input));
        $cipher = 'AES-128-ECB';//'aes-256-cbc';
        // $ivsize = openssl_cipher_iv_length($cipher);
        // $iv = openssl_random_pseudo_bytes($ivsize);
        $output = openssl_encrypt( json_encode($input), $cipher, $this->_settings['key'], OPENSSL_RAW_DATA);
        // $output = openssl_encrypt( json_encode($input), $cipher, $this->_settings['key'], OPENSSL_RAW_DATA| OPENSSL_ZERO_PADDING,$iv);
        // $crypttext = openssl_encrypt($input, 'AES-128-ECB',$key, OPENSSL_RAW_DATA);
        $signature = trim(base64_encode($output));
        Log::debug('MasterPay encrypted: '.$signature);
        return $signature;
    }

    function hashDataInBase64($input)
    {
        $output = hash('sha256',$input,true);
        $hashDataInBase64 = base64_encode($output);
        return $hashDataInBase64;
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
