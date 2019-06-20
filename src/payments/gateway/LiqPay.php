<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use cryptofx\payments\Payment;

use cryptofx\payments\sdk\LiqPay as SDKLiqPay;

/*
 ID мерчанта: 1411563
 ключ: C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU
 {
 ключи Api liqpay Привата:
Публичный ключ:
i53036014205
Приватный ключ:
8x9GibeWCz1JaYIo3O5KOxzMeWRJRvglYvweybEi
}
*/
class LiqPay extends Payment{
    protected $name = 'liqpay';

    protected $_gateUrl = 'https://www.liqpay.ua/api/3/checkout ';
    protected $_settings = [
        'private_key' => '8x9GibeWCz1JaYIo3O5KOxzMeWRJRvglYvweybEi',
        'public_key' => 'i53036014205',
        'currency' => 'USD',
        'sandbox' => false
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.preg_replace('/(crm|trade)\./','pay.',$_SERVER['SERVER_NAME']);
        if(!is_null($merchant)){
            $this->_settings['private_key'] = isset($merchant->settings['private_key'])?$merchant->settings['private_key']:$this->_settings['private_key'];
            $this->_settings['public_key'] = isset($merchant->settings['public_key'])?$merchant->settings['public_key']:$this->_settings['public_key'];
            $this->_settings['currency'] = isset($merchant->settings['currency'])?$merchant->settings['currency']:$this->_settings['currency'];
            $this->_settings['sandbox'] = isset($merchant->settings['sandbox'])?$merchant->settings['sandbox']:$this->_settings['sandbox'];
        }
        $this->_settings["successurl"]=$host.'/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/'.$this->name.'/back';
    }
    public function success($resp){}
    public function response($resp){
        $success = false;
        $response = false;
        if(!is_string($resp)){
            return Response([
                'success' => false,
                'description' => 'Incorrect postback data'
            ]);
        }
        $data = [];
        parse_str($resp,$data);
        $data = base64_decode($data['data']);
        $data=json_decode($data);

        Log::debug('LiqPay@response: data:'.json_encode($data));
        $success = $data->status == 'success';
        $description = $data->description;
        if(!$success) {
            $success = false;
            $description = "#".(isset($data->err_code)?$data->err_code:$data->status).(isset($data->err_description)?" (".$data->err_description.")":"");
        }else $success = true;
        $ret = [
            'success' => $success,
            'description' => $description,
            'method' => $data->paytype,
            'reference' => $data->payment_id,
            'orderId' => $data->order_id,
            'amount' => $data->amount,
            'currency' => $data->currency,
            'fee' => '0',
            'answer' => $data
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function sale($args,$format='form'){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = intval($args['amount']);
        $liqpay = new SDKLiqPay($this->_settings['public_key'], $this->_settings['private_key'] );
        $lpArr = [
            'action'         => 'pay',
            'amount'         => $args['amount'],
            'currency'       => $this->_settings['currency'],
            'description'    => 'Deposit of #'.$user->id.' '.$user->title,
            'order_id'       => $args['order_id'],
            'version'        => '3',
            'result_url'     => $this->_settings["successurl"]."?order_id=".$args['order_id']."&user_id=".$user->id,
            'server_url'     => $this->_settings["postbackurl"]
        ];
        Log::debug('LiqPay params', $lpArr);
        if($this->_settings["sandbox"]) $lpArr['sandbox']=1;
        $html = $liqpay->cnb_form($lpArr);
        $response = [
            "redirect"=>[
                "form"=>$html
            ]
        ];
        return $response;
    }
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
