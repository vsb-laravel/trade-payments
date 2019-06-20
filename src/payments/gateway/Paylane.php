<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
class Paylane {
    protected $_gateUrl = 'https://secure.paylane.com/order/cart.html';
    protected $_settings = [
        'separator'=>'stu9ui4h',
        'merchant_id' => 'test-sf-endika',
        'secret' => '',
        'key'=>'bea58f3f15df82f62ce6a13c37d6b2612226cfa3',
        'currency' => 'USD',
        'live' => false,
        "method"=>"cc|ch|mt|wt",
        "language"=>"en",
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => 'http://trade.cryptogriff.com/pay/paylane/success',
        'failureurl' => 'http://trade.cryptogriff.com/pay/paylane/fail',
        'postbackurl' => 'http://trade.cryptogriff.com/pay/paylane/back'
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){}
    public function success(){}
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
            'amount' => $args["amount"],
            'currency' => $this->_settings["currency"],
            'merchant_id' => $this->_settings["merchant_id"],
            // 'description' => "Customer deposit for #".$user->id." ".$user->name." ".$user->surname,
            'description' => $args["description"],
            'transaction_description' => "Customer deposit for #".$user->id." ".$user->name." ".$user->surname,
            'transaction_type' => "S",
            'back_url' => $this->_settings["successurl"],
            'language' => $this->_settings["language"],
            'hash' => ''
        ];
        $arg['hash'] = $this->signature($arg);
        $rq = new Request($arg);
        $s = '<form action="'.$this->_gateUrl.'" method="post">';
        $s.= $rq->toForm();
        $s.='<button type"submit">submit</button>';
        $s.= '</form>';
        return $s;
    }
    public function signature($args){
        return sha1(
                        $this->_settings["separator"].'|'
                        .$args["description"].'|'
                        .$args["amount"].'|'
                        .$this->_settings["currency"].'|'
                        .$args["transaction_type"]);
    }
};
?>
