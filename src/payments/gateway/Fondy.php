<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use cryptofx\payments\Payment;
/*
 ID мерчанта: 1411563
 ключ: C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU
 {
    "gate":"https://api.fondy.eu/api/checkout/redirect/",
    "merchant_id":"1411563",
    "password":"C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU",
    "currency":"USD"
}
*/
class Fondy extends Payment{
    protected $name = 'fondy';

    protected $_gateUrl = 'https://api.fondy.eu/api/checkout/redirect/';
    protected $_settings = [
        'merchant_id' => '1411563',
        'password' => 'C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU',
        'currency' => 'USD',
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
            $this->_settings['merchant_id'] = isset($merchant->settings['merchant_id'])?$merchant->settings['merchant_id']:$this->_settings['merchant_id'];
            $this->_settings['password'] = isset($merchant->settings['password'])?$merchant->settings['password']:$this->_settings['password'];
            $this->_settings['currency'] = isset($merchant->settings['currency'])?$merchant->settings['currency']:$this->_settings['currency'];
            $this->_gateUrl = isset($merchant->settings['gate'])?$merchant->settings['gate']:$this->_gateUrl;
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
    }
    public function success($resp){}
    public function response($resp){
        $success = false;
        $response = false;

        /*
        response_status	string(50)	always returns failure	failure
        error_code	integer(4)	Response decline code. Supported values see Response codes
        error_message	string(1024)	Response code description. See Response codes
        */
        $success = $this->getPostField('order_status',false);
        // $success = $this->getPostField('response_code',1000);
        // $description =$this->getPostField('order_status');
        if($success==='approved') {
            $success = true;
            $description =$this->getPostField('response_code');
        }else {
            $success = false;
            $description =$this->getPostField('response_description');
        }
        $ret = [
            'success' => $success,
            'description' => $description,
            'method' => $this->getPostField('tran_type'),
            'reference' => $this->getPostField('payment_id'),
            'orderId' => $this->getPostField('order_id'),
            'amount' => $this->getPostField('amount')/100,
            'currency' => $this->getPostField('currency'),
            'fee' => '0',
            'answer' => $response
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}

    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = intval($args['amount']*100);
        $arg = [
            "order_id"=>$args['order_id'],
            "order_desc"=>'Deposit of #'.$user->id.' '.$user->title,
            "currency"=>$this->_settings['currency'],
            "amount" => $amount,
            "merchant_id" => $this->_settings['merchant_id'],
            "response_url"=>$this->_settings["successurl"],
            "server_callback_url"=>$this->_settings["postbackurl"],
            "lifetime" => 600
        ];

        $this->signature($arg);
        Log::debug('PG:Fondy '.json_encode($arg));
        return new Request($arg);
    }
    public function signature(&$args){
        $arHash = $args;
        $params = array_filter($arHash,'strlen');
        ksort($params);
        $params = array_values($params);
        array_unshift( $params , $this->_settings['password'] );
        $params = join('|',$params);
        $args['signature'] = sha1($params);
    }
    protected function getPostField($f,$def=''){
        return isset($_POST[$f])?$_POST[$f]:$def;
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
