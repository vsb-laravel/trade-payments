<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

use Illuminate\View\View;
class Blockchain {
    /*
    [27/06/2018, 16:37:21] Sky Mechanics: https://blockchain.info/api/api_receive
[27/06/2018, 16:38:26] Sky Mechanics: xpub6BmBCQEvzvx6S58eqew6koepAdJ7s3SrawyVXMof1ExnXufgFM47vBAMSJubbReePTnztEaYhSihX7Z7R6ffDiNoUjwgMmKfRUN3x2qifCG
{
    "gate": "https://api.blockchain.info/v2/receive" ,
    "xpub": "xpub6BmBCQEvzvx6S58eqew6koepAdJ7s3SrawyVXMof1ExnXufgFM47vBAMSJubbReePTnztEaYhSihX7Z7R6ffDiNoUjwgMmKfRUN3x2qifCG",
    "key": "40939057-0b83-49a6-9fe9-911e6d308232",
    "host": "https://trade.bitooze.com",
    "view": "app.form.blockchain"
}

     */
    protected $name = 'blockchain';
    // protected $_gateUrl = 'https://secure.fxpaymentservices.com/payment/auth';


    protected $_gateUrl = 'https://api.blockchain.info/v2/receive';
    protected $_settings = [
        'xpub'=>'5b2225d63b1eafa9428b456a',
        'key'=>'chkxcMK0SIaGu9ifa2l5QsKGu9L7ZLxy',
        'host' => '',
        'gap_limit' => '100',
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
            $this->_settings['xpub'] = $merchant->settings['xpub'];
            $this->_settings['host'] = $merchant->settings['host'];
            $this->_settings['view'] = $merchant->settings['view'];
            $this->_settings['gap_limit'] = isset($merchant->settings['gap_limit'])?$merchant->settings['gap_limit']:$this->_settings['gap_limit'];
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

        //{"order_id":"5180","address":"1aY3tR7KavTaisoXoXm6MRPFbfaPDgGiu","transaction_hash":"932e1bf3f035024aad96d4095c4da1182620c1e81a6dd039d798b21806342a4a","value":"15711","confirmations":"0"}
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
        $request = $this->build($args);
        return [
            "append"=>[
                "view"=>view($this->_settings['view'],["request"=>$request->toArray()])->render()
            ],
            'data'=>$request->toArray()
        ];
    }
    public function build($args){
        // checks
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $arg = [
            'xpub'=>$this->_settings["xpub"],
            'key'=>$this->_settings["key"],
            'callback'=>$this->_settings['postbackurl']."?order_id=".$args["order_id"],
            'gap_limit'=>$this->_settings['gap_limit'],
        ];
        $response = file_get_contents($this->_gateUrl. '?' . http_build_query($arg));
        return new Request(json_decode($response,true));
    }
    public function signature($args,$prod){
        return '';
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
