<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

use Illuminate\View\View;
class AccentpayPayGate {
    protected $name = 'accentpay';
    protected $_gateUrl = 'https://gate-sandbox.accentpay.com/card/json'; // https://gate.accentpay.com
    protected $_settings = [
        'site_id'=>'1629',
        'salt'=>'b48f27a954d2bce2ea9516f06caf5af5724bbd8b',//'UbNRxMkoHCeUU8gpFRXRZMUw1yM'
        'host' => 'https://gate-sandbox.accentpay.com/card/json',
        'view' => 'app.form.accentpay',
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
                'host' => 'https://gate-sandbox.accentpay.com/card/json', // https://gate.accentpay.com
                'view' => 'app.form.accentpay'
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
            $this->_settings['view'] = $merchant->settings['view'];
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
code	integer	Код ответа о результате платежа	0
message	string	Сообщение о результате платежа	Success
pa_req	string	Данные для 3DS авторизации	eJxVUctuwjAQ/BXEFTVrB9IkaLFECw1UBYVHVfWE3MSFFJyA84K/bwwB2tvOzO54PYvLjRJisBBBrgTDiUhTvhaNKOw1j9/SWtkrc2Ua1GxTy3WaDP3+XBwYFkKlURIzahDDRLjCal4FGx5nDHlweBpPmUXbxLYRaohSqPGAWYQQl7YdF+FCYMylYJ63aEyHy8bbcoBwZjBI8jhTJ0bdR4QrwFzt2CbL9l2AsiyNgj988XhrBIlE0BrCfRE/11VaeR2jkE3kXG1b6WLutfyf0cvHbkqjvPx87c/eewi6A0OeCWYSalKTWA1qd4nV7TgIZx651Esw07BI9asLwL1+o18rWvhLYBWtEnFwYk6nkm4IxXGfxEKPINxqhPvCzyOdY5BVAe0gL6XPRTL0xGwSykKui2h23btu0o6RDssi9tlSAwRtA/XhoL5wVf27/C/Iyaua
acs_url	string	URL Acs страницы банка-эмитента карты	http://www.example.com
transaction_id	integer	Уникальный идентификатор транзакции в системе Accentpay	97223
external_id	string	Уникальный идентификатор заказа в системе Мерчанта	AAAA5555
*/


        $ret = [
            'success' => (isset($resp['confirmations']) && intval($resp['confirmations']) >=3 ),
            'description' => isset($resp['address'])?'address: '.$resp['address']:'',
            'reference' => isset($resp['transaction_hash'])?$resp['transaction_hash']:'-',
            'orderId' => isset($resp['order_id'])?$resp['order_id']:'0',
            'amount' => false,
            'currency' => 'BTC',//isset($resp['BTC'])?$resp['BTC']:'0',
            'fee' => isset($resp['value'])?$resp['value']:0,
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function sale($args,$format='form'){
        if(isset($args['exec'])){
            $response = $this->build($args);

            $code = $response->code;
            $url='/pay/'.$this->name;
            if($code!=0) $url .= '/fail?'.http_build_query(json_decode(json_encode($response),true));
            else {
                $orderId= $response->external_id;
                $url.= '/status/'.$response->external_id;
            }
            return [
                "redirect"=>[
                    "url" => $url
                    // ($res->status==='success')?'/pay/'.$this->name.'/success?':'/pay/'.$this->name.'/fail'
                    // "url"=>'/pay/pbs/success?'.$res->toUri()
                ]
            ];
            // return $this->response($response);
        }
        return [
            "append"=>[
                "view"=>view($this->_settings['view'],[
                    "page"=>[
                        "amount"=>$args["amount"],
                        "currency"=>'USD',
                        "merchant_id"=>$this->_settings['merchant_id'],
                    ]
                ])->render()
            ]
        ];
    }
    public function build($args){
        // checks
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = floatval($args['amount'])*100;
        $exp = preg_split('/\//i',$args['expire']);
        $exp_month = $exp[0];
        $exp_year = $exp[1];
        $arg = [
            'action'=>'purchase',
            'site_id'=>$this->_settings["site_id"],
            'amount'=>$amount,
            'currency'=>'USD',
            'external_id'=>$args['order_id'],//	Да	string	Уникальный идентификатор заказа в системе Мерчанта. Если запросу на оплату предшествовал запрос create_order - укажите external_id из этого запроса	AA00033
            'card'=>$args['card'],//	Да	string(19)	Номер банковской карты Пользователя	5555555555554444
            'exp_month'=>$exp_month,//	Да	string(2)	Месяц срока окончания действия карты	01
            'exp_year'=>"20".$exp_year,//	Да	string(4)	Год срока окончания действия карты	2018
            'cvv'=>$args['cvv'],//	Да	string(4)	CVV код карты	123
            'holder'=>$args['holder'],//	Да	string(256)	Имя держателя (как на карте)	IVAN IVANOV
            'customer_ip'=>$args['ip'],//	Да	inet (ipv4 or ipv6)	IP адрес покупателя, который оставил заявку на оплату	1.2.3.4
            'description'=>"Deposit on {$user->title} {$args['amount']}",//	Нет	string(512)	Описание заказа	test_description
            'signature'=>''
        ];
        $arg['signature'] = $this->signature($arg,null);
        $request = new Request($arg);
        $response = $request->post($this->_gateUrl,'query');
        Log::debug('Accentpay request: '.$request->toJson());
        Log::debug('Accentpay response: '.$response);
        $response = json_decode($response);
        return $response;
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
