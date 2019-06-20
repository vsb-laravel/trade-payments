<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

use Illuminate\View\View;
class Accentpay {
    protected $name = 'accentpay';
    protected $_gateUrl = 'https://terminal-sandbox.accentpay.com'; // https://terminal.accentpay.com
    protected $_settings = [
        'site_id'=>'1629',
        'salt'=>'b48f27a954d2bce2ea9516f06caf5af5724bbd8b',//'UbNRxMkoHCeUU8gpFRXRZMUw1yM'
        'host' => 'https://terminal-sandbox.accentpay.com',
        'merchant_id'=>0,
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
        if(is_null($merchant)){
            $settings = [
                'site_id'=>'1629',
                'salt'=>'b48f27a954d2bce2ea9516f06caf5af5724bbd8b',//'UbNRxMkoHCeUU8gpFRXRZMUw1yM'
                'host' => 'https://terminal-sandbox.accentpay.com', // https://terminal.accentpay.com
            ];
            $merchant = Merchant::create([
                'name'=>$this->name,
                'title'=>'Accentpay',
                'enabled'=>1,
                'class'=>'cryptofx\payments\gateway\Accentpay',
                'settings'=>json_encode($settings)
            ]);
        }
        if(!is_null($merchant)){
            $this->_gateUrl = $merchant->settings['host'];
            $this->_settings['salt'] = $merchant->settings['salt'];
            $this->_settings['site_id'] = $merchant->settings['site_id'];
            $this->_settings['host'] = $merchant->settings['host'];
            $this->_settings['merchant_id'] = $merchant->id;
            $host = $this->_settings['host'];
            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
        $this->_settings["pendingurl"]=$host.'/pay/'.$this->name.'/pending';
    }
    public function success($resp){}
    public function response($resp){

/*
code	integer	Код ответа о результате платежа	0
message	string	Сообщение о результате платежа	Success
acquirer_id	string	Уникальный номер транзакции в системе Внешнего процессора	89151432
transaction_id	integer	Уникальный идентификатор транзакции в системе Accentpay	97223
processor_id	integer	Идентификатор Внешнего Процессора в системе Accentpay	16
processor_code	string	Код ответа от Внешнего Процессора	0
processor_message	string	Сообщение от Внешнего Процессора	Success
amount	integer	Сумма платежа в валюте сайта (отображается в минорных единицах)	10000
currency	ISO 4217	Валюта платежа	RUB
real_amount	integer	Сумма платежа в валюте канала (отображается в минорных единицах)	10000
real_currency	ISO 4217	Валюта канала	RUB
external_id	string	Уникальный идентификатор заказа в системе Мерчанта	AAAA5555
authcode	string	Авторизационный код банка-эмитента	9N3A0D
*/


        $ret = [
            'success' => (isset($resp['code']) && intval($resp['code']) ===0 ),
            'description' => isset($resp['message'])?$resp['message']:'',
            'reference' => isset($resp['transaction_id'])?$resp['transaction_id']:'-',
            'orderId' => isset($resp['external_id'])?$resp['external_id']:'0',
            'amount' => intval($resp['amount'])/100,
            'currency' => $resp['currency'],//isset($resp['BTC'])?$resp['BTC']:'0',
            'fee' => 0
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function sale($args,$format='form'){
        $request = $this->build($args);
        return  [
            "redirect"=>[
                "form"=>'<form action="'.$this->_gateUrl.'" method="POST" enctype="application/x-www-form-urlencoded">'.$request->toForm().'</form>'
            ]
        ];
    }
    public function build($args){
        // checks
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = floatval($args['amount'])*100;
        $arg = [
            // 'action'=>'purchase',
            'site_id'=>$this->_settings["site_id"],
            'amount'=>$amount,
            'currency'=>'USD',
            'external_id'=>$args['order_id'],
            'description'=>"Deposit on {$user->title} {$args['amount']}",//	Нет	string(512)	Описание заказа	test_description
            'signature'=>''
        ];
        $arg['signature'] = $this->signature($arg,null);

        return new Request($arg);
    }
    public function signature($args,$prod){
        $string = "";
        ksort($args);
        foreach ($args as $key => $value) {
            if(!strlen($value) || empty($value)) continue;
            $val = $value;
            $string.="{$key}:{$val};";
        }
        $string.= $this->_settings["salt"];
        return sha1($string);
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
