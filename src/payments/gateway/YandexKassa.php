<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use YandexCheckout\Client;
class YandexKassa {
    protected $name = 'yandexkassa';
    // INSERT INTO `merchants` (`name`, `title`, `enabled`, `class`, `settings`) VALUES ('yandexkassa', 'Yandex.Kassa', '1', 'cryptofx/payments/gateway/YandexKassa', '{\"shopId\":\"\",\"secret\":\"\",\"key\":\"\",\"currency\":\"RUB\"}');

    protected $_settings = [
        'shopId'=>'',
        'key'=>'',
        'secret' => '',
        'currency' => 'USD',
        'host' => '', // to del
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
            $this->_settings['key'] = $merchant->settings['key'];
            $this->_settings['shopId'] = $merchant->settings['shopId'];
            $this->_settings['secret'] = $merchant->settings['secret'];
            $this->_settings['currency'] = $merchant->settings['currency'];
            $host = isset($this->_settings['host'])?$this->_settings['host']:$host;
            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }

        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
        $this->_settings["pendingurl"]=$host.'/pay/'.$this->name.'/pending';
        $this->client = new Client();
        $this->client->setAuth($this->_settings['shopId'], $this->_settings['secret']);
    }
    public function sale($args,$format='form'){
        $request = $this->build($args);
        $response = [
            "redirect"=>[
                "url"=>isset($request->confirmation->confirmation_url)?$request->confirmation->confirmation_url:'',
                "method"=>"GET"
            ]
        ];
        return $response;
    }
    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $amount = number_format(floatval($args['amount']),2,'.','');
        $user = $args['user'];
        $payment = $this->client->createPayment(
            [
                "amount"=>[
                    "value"=>floatval($amount),
                    "currency"=>$this->_settings["currency"]
                ],
                "confirmation"=>[
                    'type' => 'redirect',
                    'return_url' => $this->_settings["successurl"],
                ],
                "capture"=>true,
                'description' => isset($args['description'])?$args['description']:$args["order_id"],
                "metadata"=>[
                    "order_id"=>$args["order_id"]
                ]
            ],
            uniqid('',true)
        );
        return $payment;
    }
    public function success($resp){}
    public function response($respStr){
        // $argType = 'string';
        // if(is_array($respStr)) $argType='array';
        // else if(is_object($respStr)) $argType='object';
        // Log::debug('YandexKassa::response <'.($argType).'>'.json_encode($respStr));
        // $resp = ($argType == 'string')?json_decode($respStr,true):$respStr;

        $source = file_get_contents('php://input');
        $resp = json_decode($source, true);
        $ret = [
            'success' => (isset($resp['object']['status']) && $resp['object']['status'] == 'succeeded'),
            'description' => isset($resp['object']['description'])?$resp['object']['description']:'',
            'reference' => isset($resp['object']['id'])?$resp['object']['id']:'-',
            'orderId' => isset($resp['object']['metadata']["order_id"])?$resp['object']['metadata']["order_id"]:'0',
            'amount' => isset($resp['object']['amount']['value'])?$resp['object']['amount']['value']:0,
            'currency' => isset($resp['object']['amount']['currency'])?$resp['object']['amount']['currency']:$this->_settings['currency'],
            'fee' => '0',
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function signature($args,$prod){
        return '';
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
