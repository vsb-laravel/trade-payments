<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
class Advcash {
    protected $_gateUrl = 'https://wallet.advcash.com/sci/';
    protected $_settings = [
        'merchant_id' => 'gains.holding@gmail.com',
        'merchant_name' => 'Crypto currency deal',
        'password' => '36L6m2R@vh',
        'currency' => 'USD',
        "language"=>"en",
        "method"=>'ADVANCED_CASH',
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => 'http://trade.cryptogriff.com/pay/advcash/success',
        'failureurl' => 'http://trade.cryptogriff.com/pay/advcash/fail',
        'postbackurl' => 'http://trade.cryptogriff.com/pay/advcash/back'
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){}
    public function success($resp){}
    public function response($resp){
        $ret = [
            'success' => (isset($resp['ac_transaction_status']) && $resp['ac_transaction_status']=='COMPLETED'),
            'reference' => isset($resp['ac_transfer'])?$resp['ac_transfer']:'-',
            'orderId' => isset($resp['ac_order_id'])?$resp['ac_order_id']:'0',
            'amount' => isset($resp['ac_amount'])?$resp['ac_amount']:'0',
            'currency' => isset($resp['ac_merchant_currency'])?$resp['ac_merchant_currency']:'0',
            'fee' => isset($resp['ac_fee'])?$resp['ac_fee']:'0',
        ];

        return new Response($ret);
    }
    public function fail(){}
    public function back(){}
    public function sale($args){
        $request = $this->build($args);
        $response = [
            "redirect"=>[
                "form"=>$request
            ]
        ];
        return $response;
    }
    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $arg = [
            'ac_account_email' => $this->_settings["merchant_id"],
            'ac_sci_name' => $this->_settings["merchant_name"],
            'ac_amount' => $args["amount"],
            // 'ac_ps' => $this->_settings['method'],
            'ac_currency' => $this->_settings["currency"],
            'ac_order_id' => $args["order_id"],
            'ac_sign' => ''
        ];
        $arg['ac_sign'] = $this->signature($arg);
        $rq = new Request($arg);
        $s = '<form action="'.$this->_gateUrl.'" method="GET">';
        $s.= $rq->toForm();
        // $s.='<button type"submit">submit</button>';
        $s.= '</form>';
        return $s;
    }
    public function signature($args){
        return hash('sha256',
                        $args["ac_account_email"].':'
                        .$args["ac_sci_name"].':'
                        .$args["ac_amount"].':'
                        .$this->_settings["currency"].':'
                        .$this->_settings["password"].':'
                        .$args["ac_order_id"]);
    }
};
?>
