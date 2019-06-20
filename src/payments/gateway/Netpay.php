<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
class Netpay {
    protected $_gateUrl = 'https://services.netpay-intl.com/hosted';
    protected $_settings = [
        'merchant_id' => '7804455',
        'merchant_name' => 'Netpay for cryptopro1.com',
        'password' => 'GPEWB2PCCQ',
        'currency' => 'USD',
        "language"=>"en",
        "method"=>'ADVANCED_CASH',
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => '',
        'failureurl' => '',
        'postbackurl' => ''
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings["successurl"]='https://'.$_SERVER['SERVER_NAME'].'/pay/netpay/success';
        $this->_settings["failureurl"]='https://'.$_SERVER['SERVER_NAME'].'/pay/netpay/fail';
        $this->_settings["postbackurl"]='https://'.$_SERVER['SERVER_NAME'].'/pay/netpay/back';
    }
    public function success($resp){}
    public function response($resp){
        $ret = [
            'success' => (isset($resp['replyCode']) && $resp['replyCode']=='000'),
            'description' => isset($resp['replyDesc'])?$resp['replyDesc']:'',
            'reference' => isset($resp['trans_id'])?$resp['trans_id']:'-',
            'orderId' => isset($resp['trans_refNum'])?$resp['trans_refNum']:'0',
            'amount' => isset($resp['trans_amount'])?$resp['trans_amount']:'0',
            'currency' => isset($resp['trans_currency'])?$resp['trans_currency']:'0',
            'fee' => '0',
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
            'merchantID' => $this->_settings["merchant_id"],
            'trans_amount' => $args["amount"],
            'trans_currency' => $this->_settings["currency"],
            'trans_type' => '0',
            'trans_installments' => '1',
            'trans_refNum' => $args['order_id'],
            'url_notify'=>$this->_settings["postbackurl"],
            'url_redirect'=>$this->_settings["successurl"],
            'signature'=>''
        ];
        $arg['signature'] = $this->signature($arg);
        $rq = new Request($arg);
        $s = '<form action="'.$this->_gateUrl.'" method="GET">';
        $s.= $rq->toForm();
        // $s.='<button type"submit">submit</button>';
        $s.= '</form>';
        return $s;
    }
    public function signature($args){
        return base64_encode(hash("sha256",
            $this->_settings["merchant_id"].
            $args['trans_amount'].
            $args['trans_currency'].
            $args['trans_type'].
            $args['trans_installments'].
            $args['trans_refNum'].
            $args['url_notify'].
            $args['url_redirect'].
            $this->_settings["password"],
            true));
    }
};
?>
