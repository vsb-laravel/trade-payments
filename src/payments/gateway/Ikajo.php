<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
class Ikajo {
    protected $name = 'ikajo';
    // protected $_gateUrl = 'https://secure.fxpaymentservices.com/payment/auth';
    protected $_gateUrl = 'https://secure.payinspect.com/post';
    protected $_settings = [
        'merchant_id' => 'CH9UCLHMTB',
        'merchant_name' => 'Webpayments for amotrader.com',
        'password' => 'pdvWNNS3jDRSYDAfxuGsb2YSr95PGQqy',
        'currency' => 'USD',
        "language"=>"en",
        "method"=>'CC',
        'host' => 'https://trade.amotrader.com',
        'successurl' => '',
        'failureurl' => '',
        'postbackurl' => ''
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(!is_null($merchant)){
            $this->_gateUrl = $merchant->settings['gate'];
            $this->_settings['merchant_id'] = $merchant->settings['merchant_id'];
            $this->_settings['merchant_name'] = $merchant->settings['merchant_name'];
            $this->_settings['password'] = $merchant->settings['password'];
            $this->_settings['host'] = $merchant->settings['host'];
            $this->_settings['method'] = $merchant->settings['method'];
            $this->_settings['language'] = $merchant->settings['language'];
            $this->_settings['currency'] = $merchant->settings['currency'];
            $host = $this->_settings['host'];
            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }

        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
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
            Log::debug("WebPayments form:".$form);
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
        $prod = base64_encode(
            json_encode(
                [
                    'amount'=>$amount,
                    'currency'=>'USD',
                    'description'=>'Deposit #'.$user->id
                ]
            )
        );
        $arg = [
            'key' => $this->_settings["merchant_id"],
            'payment' => $this->_settings["method"],
            'order' => $args['order_id'],
            'data' => $prod,
            'url'=>$this->_settings["successurl"],
            'error_url'=>$this->_settings["failureurl"],

            'first_name'=>$user->name,
            'last_name'=>$user->surname,
            'email'=>$user->email,
            'phone'=>$user->phone,
            'country'=>$user->country,

            'sign'=>''
        ];
        $arg['sign'] = $this->signature($arg,$prod);
        return new Request($arg);
    }
    public function signature($args,$prod){
        return md5 (strtoupper(
            strrev($this->_settings["merchant_id"])
            .strrev($this->_settings["method"])
            .strrev($prod)
            .strrev($this->_settings["successurl"])
            .strrev($this->_settings["password"])
        ));

    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
