<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use cryptofx\PayException;
/*
url = www.cryptogriff.com
Merchant ID 0015858621
Secret Code 09be396f86a4bc1bf226c99d026909ab
Separator = ##

gateway_id
0 - test gateway
1 - live gateway

Use this test account username for test gateway.
username : testmegatransfer@gmail.com
password : testmt@1234

BO

$items = $_POST["items"];
$quantity = $_POST["quantity"];
$amount =  $_POST["amount"];
$currency = $_POST["currency"];
$client_name = $_POST["client_name"];
$client_country = $_POST["client_country"];
$client_city = $_POST["client_city"];
$client_email = $_POST["client_email"];
$reg_date = $_POST["client_regdate"];
$url = "https://www.megatransfer.com/";
$merchant_id = "526600186654";
$secret_code = "6c417hg8eaef344c41b8ffggh56064cdfe13101dc28bbnx9b03bbb34c7e96dbdf4";
$separator = "101136960129";
$order_id = strtotime("now");
$total_amount = (int) $quantity * (float) $amount;
$total_amount = sprintf("%1.2f",$total_amount);
$signature = hash("sha256",$secret_code.$separator.$merchant_id.$separator.$items.$separator.$quantity.$separator.$amount.$separator.$total_amount.$separator.$currency.$separator.$secret_code);

<html>
<body onLoad="document.form.submit();">
<form id="form" name="form" method="post" action="https://www.megatransfer.com/payments/">

</form>
</body>
</html>
<!--
{"items":"Clothes","quantity":"2","amount":"2.00","currency":"USD","total_amount":"4.00","merchant_id":"526600186654",
"order_id":"1439280592","status":"failed","payment_method":"CC","message":"Internal error on the system. Please contact support."}

{"items":"Clothes","quantity":"2","amount":"50.00","currency":"USD","total_amount":"100.00","merchant_id":"526600186",
"order_id":"1439280592","token":"66baa1bf7680613584b0f154bca39f0e","transaction_id":"59037524897","status":"successful","payment_method":"WT","amount_credited":2.99} -->
*/

class MegaTransfer {
    protected $_gateUrl = 'https://merchant.payb.lv/xml_service';
    protected $_settings = [
        'separator'=>'##',
        'merchant_id' => '0015858621',
        'secret' => '09be396f86a4bc1bf226c99d026909ab',
        'currency' => 'USD',
        'live' => false,
        "method"=>"cc|ch|mt|wt",
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => 'http://trade.cryptogriff.com/pay/success',
        'failureurl' => 'http://trade.cryptogriff.com/pay/fail',
        'postbackurl' => 'http://trade.cryptogriff.com/pay/back'
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_responseDom = new \DomDocument();
        $this->_settings = array_merge($this->_settings,$a);
    }
    public function success(){}
    public function fail(){}
    public function back(){}
    public function deposit($args){
        $request = $this->build($args);
        $response = $this->fetch($request);
        return $response;
    }
    public function build($args){
        // checks
        /*
        <input type="hidden" name="items" value="<?php echo $items?>"/>
        <input type="hidden" name="quantity" value="<?php echo $quantity?>"/>
        <input type="hidden" name="amount" value="<?php echo $amount?>"/>
        <input type="hidden" name="currency" value="<?php echo $currency?>"/>
        <input type="hidden" name="total_amount" value="<?php echo $total_amount?>"/>
        <input type="hidden" name="merchant_id" value="<?php echo $merchant_id?>"/>
        <input type="hidden" name="order_id" value="<?php echo $order_id?>"/>
        <input type="hidden" name="client_name" value="<?php echo $client_name?>"/>
        <input type="hidden" name="client_country" value="<?php echo $client_country?>"/>
        <input type="hidden" name="client_city" value="<?php echo $client_city?>"/>
        <input type="hidden" name="client_email" value="<?php echo $client_email?>"/>
        <input type="hidden" name="client_regdate" value="<?php echo $reg_date?>"/>
        <input type="hidden" name="url" value="<?php echo $url?>"/>
        <input type="hidden" name="signature" value="<?php echo $signature?>"/>
        //FOR TEST GATEWAY
        <input type="hidden" name="gateway_id" value="0"/>
        //FOR LIVE GATEWAY
        <input type="hidden" name="gateway_id" value="1"/>
        <input type="hidden" name="payment_method" value="cc|ch|mt|wt"/>
        <input type="hidden" name="success_url" value="https://www.megatransfer.com/success/"/>
        <input type="hidden" name="fail_url" value="https://www.megatransfer.com/fail/"/>
        <input type="hidden" name="callback_url" value="https://www.megatransfer.com/callback/"/>
        */
        // if(!isset($args['method'])) throw new PayException('No method assigned');
        if(!isset($args['amount'])) throw new PayException('No amount assigned');
        if(!isset($args['user'])) throw new PayException('No user assigned');
        if(!isset($args['account'])) throw new PayException('No account assigned');
        $ret = [
            "items" => "Customer deposit for #"$user->id," ".$user->name." ".$user->surname,
            "quantity" => "1",
            "amount" => $args["amount"],
            "currency" => $this->_settings["currency"],
            "total_amount" => $args["amount"],
            "merchant_id" => $this->_settings["merchant_id"],
            "order_id" => $args["order_id"],
            "client_name" => $user->name." ".$user->surname,
            "client_country" => $user->country,
            "client_city" => $user->country,
            "client_email" => $user->email,
            "client_regdate" => $user->created_at,
            "url" => $this->_settings["host"],
            "signature" => "",
            //FOR TEST GATEWAY
            "gateway_id" => ($this->_settings["live"])?1:0,
            "payment_method" => $this->_settings["method"],
            "success_url" => $this->_settings["successurl"],
            "fail_url" => $this->_settings["failureurl"],
            "callback_url" => $this->_settings["postbackurl"],
        ];
        $ret['signature'] = $this->signature($ret);
        return http_build_query($ret);
    }
    public function fetch($data){
		$headers = array(
		    'Content-type: text/xml',
		    'Content-length: '.strlen($data)
		);
		$ch = curl_init();
        Log::debug($this->_gateUrl);
        Log::debug($data);
		curl_setopt($ch, CURLOPT_URL, $this->_gateUrl);
		curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-type: text/xml',
            'Referer: cryptogriff.com',
            'Content-length: '.strlen($data)]
        );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
        if(curl_errno($ch)){
            Log::error("curl_error:".curl_error($ch));
        }
        Log::debug($response);
        $this->_response = $response;
		curl_close($ch);
        $this->getError();
        return $this->getResponse();
	}
    public function signature($args){
        return hash("sha256",
                        $this->_settings["secret"].$this->_settings["separator"]
                        .$this->_settings["merchant_id"].$this->_settings["separator"]
                        .$args["items"].$this->_settings["separator"]
                        .$args["quantity"].$this->_settings["separator"]
                        .$args["amount"].$this->_settings["separator"]
                        .$args["total_amount"].$this->_settings["separator"]
                        .$this->_settings["currency"].$this->_settings["separator"]
                    .$this->_settings["secret"]);
    }
    protected function getElementByTagName($tagname) {
        $node = $this->_responseDom->getElementsByTagName($tagname);
		return ($node->length)?$node->item(0)->nodeValue:false;
	}
    protected function getError(){

    }
    protected function getResponse(){
        $res = [];
        if($this->getElementByTagName( 'Status') == 'created'){
			// сохраняем в базу
			// $res['OrderID'] = $this->getElementByTagName( 'OrderID');
			// $res['ReferenceID'] = $this->getElementByTagName( 'ReferenceID');
			// $res['AmountMerchantCurrency'] = $this->getElementByTagName( 'AmountMerchantCurrency');
			// $res['MerchantCurrency'] = $this->getElementByTagName( 'MerchantCurrency');
			// $res['amount'] = $this->getElementByTagName( 'AmountBuyerCurrency');
			// $res['BuyerCurrency'] = $this->getElementByTagName( 'BuyerCurrency');
			// $res['RedirectURL'] = $this->getElementByTagName( 'RedirectURL');
		}
        else throw new PayException('Неизвестная ошибка ');
        return $res;
    }
};
?>
