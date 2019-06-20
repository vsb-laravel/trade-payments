<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
class Interkassa {
    protected $name = 'interkassa';
    // protected $_gateUrl = 'https://secure.fxpaymentservices.com/payment/auth';


    protected $_gateUrl = 'https://sci.interkassa.com/';
    protected $_settings = [
        'user_id'=>'5b2225d63b1eafa9428b456a',
        'key'=>'chkxcMK0SIaGu9ifa2l5QsKGu9L7ZLxy',
        'merchant_id' => '5b2225d63b1eafa9428b456a',
        'currency' => 'USD',
        'host' => '',
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
            $this->_settings['key'] = $merchant->settings['key'];
            $this->_settings['merchant_id'] = $merchant->settings['merchant_id'];
            $this->_settings['host'] = $merchant->settings['host'];
            $this->_settings['currency'] = $merchant->settings['currency'];
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
        $ret = [
            'success' => (isset($resp['ik_inv_st']) && $resp['ik_inv_st'] == 'success'),
            'description' => isset($resp['description'])?$resp['description']:'',
            'reference' => isset($resp['ik_pm_no'])?$resp['ik_pm_no']:'-',
            'orderId' => isset($resp['ik_pm_no'])?$resp['ik_pm_no']:'0',
            'amount' => isset($resp['ik_co_rfn'])?$resp['ik_co_rfn']:(isset($resp['ik_am'])?$resp['ik_am']:'0'),
            'currency' => isset($resp['ik_cur'])?$resp['ik_cur']:'0',
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
            Log::debug("Interkassa form:".$form);
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
        $arg = [
            'ik_co_id'=>$this->_settings["merchant_id"],
            'ik_pm_no'=>$args["order_id"],
            'ik_am'=>$amount,
            'ik_cur'=>$this->_settings["currency"],
            'ik_desc'=>'Deposit #'.$user->id." ".$user->title,
            'ik_ia_u'=>$this->_settings['postbackurl'],
            'ik_ia_m'=>'POST',
            'ik_suc_u'=>$this->_settings['successurl'],
            'ik_suc_m'=>'GET',
            'ik_fal_u'=>$this->_settings['failureurl'],
            'ik_fal_m'=>'GET',
            'ik_pnd_u'=>$this->_settings['pendingurl'],
            'ik_pnd_m'=>'GET',
        ];
        return new Request($arg);
    }
    public function signature($args,$prod){
        return '';
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
