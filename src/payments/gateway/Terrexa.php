<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

use Illuminate\View\View;
class Terrexa {
    protected $name = 'terrexa';
    protected $_gateUrl = 'https://www.terrexa.com/api/v1/psp_orders/init_payment_request'; // https://terminal.accentpay.com
    protected $_settings = [
        'host' => 'https://www.terrexa.com/api/v1/psp_orders/init_payment_request',
        'widget' => 'https://widget.terrexa.com',//?brid={broker id}&transactionId={TransactionID}',

        'brokerid'=>2382,
        'secretkey'=>'435jklh32l45hlkj4h5lk34j445kj89l7jh534b6798',
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(is_null($merchant)){
            $settings = [

                'brokerid'=>"2382",
                'secretkey'=>'435jklh32l45hlkj4h5lk34j445kj89l7jh534b6798',//'UbNRxMkoHCeUU8gpFRXRZMUw1yM'
                'host' => 'https://www.terrexa.com/api/v1/psp_orders/init_payment_request', // https://terminal.accentpay.com
                'widget' => 'https://widget.terrexa.com',//?brid={broker id}&transactionId={TransactionID}',
            ];
            $merchant = Merchant::create([
                'name'=>$this->name,
                'title'=>'Accentpay',
                'enabled'=>1,
                'class'=>'cryptofx\payments\gateway\Terrexa',
                'settings'=>json_encode($settings)
            ]);
        }
        if(!is_null($merchant)){
            $this->_gateUrl = $merchant->settings['host'];
            $this->_settings['secretkey'] = $merchant->settings['secretkey'];
            $this->_settings['brokerid'] = $merchant->settings['brokerid'];
            $this->_settings['host'] = $merchant->settings['host'];
            $this->_settings['widget'] = $merchant->settings['widget'];
            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
        $this->_settings["pendingurl"]=$host.'/pay/'.$this->name.'/pending';
    }
    public function success($resp){}
    public function response($resp){
        $json = file_get_contents('php://input');
        $resp = json_decode($json,true);
        $ret = [
            'pending' => (isset($resp['Status']) && in_array($resp['Status'],['setup','pending','validation','pending_for_documents','pending_for_level2_documents','pending_for_level3_documents','inprogress']) ),
            'success' => (isset($resp['Status']) && $resp['Status'] == 'success' ),
            'description' => isset($resp['ResultDescription'])?$resp['ResultDescription']:'',
            'reference' => isset($resp['TransactionID'])?$resp['TransactionID']:'-',
            'orderId' => isset($resp['Reference'])?$resp['Reference']:'0',
            'amount' => false,
            'currency' => false,
            'fee' => 0
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function sale($args,$format='form'){
        $request = $this->build($args);
        $initBody = $request->post($this->_settings['host'],'json');
        $init = json_decode($initBody);
        if(isset($init->Status)){
            if(in_array($init->Status,['init'])){
                return  [
                    "redirect"=>[
                        "url"=>$this->_settings['widget'].'?brid='.$this->_settings['brokerid'].'&transactionId='.$init->TransactionID
                    ]
                ];
            }
        }
        return  [
            "error"=>[
                "code"=>isset($init->ResultCode)?$init->ResultCode:500,
                'message'=>$init->ResultDescription
            ]
        ];
    }
    public function build($args){
        // checks
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = number_format(floatval($args['amount']),2,'.','');
        $arg = [
            // 'action'=>'purchase',
            'MerchantID'=>$this->_settings["brokerid"],
            'Signature'=>'',
            'Reference'=>"".$args['order_id'],
            'Amount'=>$amount,
            'Currency'=>'USD',
            'NotificationUrl'=>$this->_settings["postbackurl"],
            'FirstName'=>$user->name,
            'LastName'=>$user->surname,
            'Email'=>$user->email,
            'Phone'=>$user->phone,
            // 'Description'=>"Deposit on {$user->title} {$args['amount']}",//	Нет	string(512)	Описание заказа	test_description

        ];
        $arg['Signature'] = $this->signature($arg,null);

        return new Request($arg);
    }
    public function signature($args,$prod){
        $string = $args['MerchantID'].$args['Reference'].$this->_settings["secretkey"];
        return hash('sha384',$string);
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
