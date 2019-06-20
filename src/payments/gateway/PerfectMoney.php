<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

class PerfectMoney {
    protected $_gateUrl = 'https://perfectmoney.is/api/step1.asp';
    protected $_settings = [
        'merchant_id' => 'U15358434',
        'merchant_name' => 'Crypto currency deal',
        'password' => '-Ywg20s76f',
        'currency' => 'USD',
        "language"=>"en",
        "method"=>'ADVANCED_CASH',
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => 'http://trade.cryptogriff.com/pay/perfectmoney/success',
        'failureurl' => 'http://trade.cryptogriff.com/pay/perfectmoney/fail',
        'postbackurl' => 'http://trade.cryptogriff.com/pay/perfectmoney/back'
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
        /*
        <form action="https://perfectmoney.is/api/step1.asp" method="POST">
        <input type="hidden" name="PAYEE_ACCOUNT" value="U15358434">
        <input type="hidden" name="PAYEE_NAME" value="Crypto currency deal">
        <input type="text" name="PAYMENT_ID" value=""><BR>
        <input type="text" name="PAYMENT_AMOUNT" value=""><BR>
        <input type="hidden" name="PAYMENT_UNITS" value="USD">
        <input type="hidden" name="STATUS_URL" value="https://trade.cryptogriff.com/pay/perfectmoney/back">
        <input type="hidden" name="PAYMENT_URL" value="https://trade.cryptogriff.com/pay/perfectmoney/success">
        <input type="hidden" name="PAYMENT_URL_METHOD" value="GET">
        <input type="hidden" name="NOPAYMENT_URL" value="https://trade.cryptogriff.com/pay/perfectmoney/fail">
        <input type="hidden" name="NOPAYMENT_URL_METHOD" value="POST">
        <input type="hidden" name="SUGGESTED_MEMO" value="">
        <input type="hidden" name="BAGGAGE_FIELDS" value="">
        <input type="submit" name="PAYMENT_METHOD" value="Pay Now!">
        </form>
        */
        $arg = [
            'PAYEE_ACCOUNT' => $this->_settings["merchant_id"],
            'PAYEE_NAME' => $this->_settings["merchant_name"],
            'PAYMENT_AMOUNT' => $args["amount"],
            'PAYMENT_UNITS' => $this->_settings["currency"],
            'PAYMENT_ID' => $args["order_id"],
            'PAYMENT_URL'=>$this->_settings["successurl"],
            'NOPAYMENT_URL'=>$this->_settings["failureurl"],
            // 'V2_HASH' => ''
        ];
        // $arg['V2_HASH'] = $this->signature($arg);
        $rq = new Request($arg);
        $s = '<form action="'.$this->_gateUrl.'" method="POST">';
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
