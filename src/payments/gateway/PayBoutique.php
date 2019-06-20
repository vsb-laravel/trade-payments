<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\PayException;;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
/*
Uhbaa
MerchantID = 481
UserID = 996
Username: cryptogriff_api
password: JAk_14bBo;3_
new: S3SX9Pz\!9lN

BO

{"gate":"https://merchant.payb.lv/xml_service","userid":"1047","merchantid":"505","password":"bit_6_95XGuYgx","currency":"USD","live":true}
{     gate:"https://merchant.payb.lv/xml_service",     userid:"1047",     merchantid:"505",     password:"bit_6_95XGuYgx",     currency:"USD",     live:true }

bit2change_api
6;_95XGuYg:x

bit_6_95XGuYgx
*/

class PayBoutique {
    protected $_gateUrl = 'https://merchant.payb.lv/xml_service';
    protected $_settings = [
        'UserID'=>'996',
        'MerchantID' => '481',
        'password' => 'S3SX9Pz\!9lN',
        'currency' => 'USD',
        'live' => "true",
        'host' => '',
        'successurl' => '',
        'failureurl' => '',
        'postbackurl' => ''
    ];
    protected $_host='';
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_responseDom = new \DomDocument();
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name','payboutique')->first();
        if(!is_null($merchant)){
            $this->_settings['UserID'] = $merchant->settings['userid'];
            $this->_settings['MerchantID'] = $merchant->settings['merchantid'];
            $this->_settings['password'] = $merchant->settings['password'];
        }
        $host = preg_replace('/^(crm|trade)\./','',$_SERVER['SERVER_NAME']);
        $this->_settings["host"]='https://trade.'.$host;
        $this->_settings["successurl"]='https://trade.'.$host.'/pay/payboutique/success';
        $this->_settings["failureurl"]='https://trade.'.$host.'/pay/payboutique/fail';
        $this->_settings["postbackurl"]='https://trade.'.$host.'/pay/payboutique/back';
        $this->_host = $host;
        Log::debug('payboutique:'.json_encode($this->_settings));
    }
    public function success(){}
    public function fail(){}
    public function back(){}
    public function response($data){
        $data = is_array($data)?$data[0]:$data;
        $this->_response = $data;
        $this->_responseDom->loadXML($data);
        Log::debug('response DOM:'.$this->_responseDom->saveXML());
        $errId = $this->getElementByTagName( 'ErrorID');
        $res = [
            'success'=>false,
            'merchant_id' => $this->getElementByTagName('MerchantID'),
            'merchantReference' => $this->getElementByTagName('MerchantReference'),
            'orderId' => $this->getElementByTagName('OrderID'),
            'method' => $this->getElementByTagName('PaymentMethod'),
            'merchantAmount' => $this->getElementByTagName('AmountMerchantCurrency'),
            'merchantCurrency' => $this->getElementByTagName('MerchantCurrency'),
            'amount' => $this->getElementByTagName('AmountBuyerCurrency'),
            'currency' => $this->getElementByTagName('BuyerCurrency'),
            'status' => $this->getElementByTagName('Status'),
            'reference_id' => $this->getElementByTagName('ReferenceID')
        ];
        if($res['status'] == 'captured'){
            $res['success'] = true;

        }else{
            $res['error'] = $res['status'];
        }
        return new Response($res);
        // return json_decode(json_encode($res));
    }
    public function deposit($args){
        $request = $this->build($args);
        $response = $this->fetch($request);
        return $response;
    }
    public function build($args){
        // checks
        if(!isset($args['method'])) throw new PayException('No method assigned');
        if(!isset($args['amount'])) throw new PayException('No amount assigned');
        if(!isset($args['user'])) throw new PayException('No user assigned');
        if(!isset($args['account'])) throw new PayException('No account assigned');
        //params
        $time = date('c');
		$expireTime = date('c', strtotime("+1 day", time()));
        $user = $args['user'];
        $account = $args['account'];

        $description = $user->email.' #'.$account->id.' deposit';
        $product = 'Deposit on '.$this->_settings['host'];
        //request
        $xml = '<Message version="0.5">';
		$xml.= '<Header>';
		$xml.=    '<Identity>';
		$xml.=        '<UserID>'. $this->_settings['UserID'] .'</UserID>';
		$xml.=        '<Signature>'. $this->signature($time) .'</Signature>';
		$xml.=    '</Identity>';
        $xml.=    '<Time>'. $time .'</Time>';
		$xml.= '</Header>';
		$xml.= '<Body type="GetInvoice" live="'.$this->_settings['live'].'">';
		$xml.=    '<Order paymentMethod="'. strtolower($args['method']) .'" buyerCurrency="'. $this->_settings['currency'] .'">';
		$xml.=        '<MerchantID>'. $this->_settings['MerchantID'] .'</MerchantID>';
		$xml.=        '<AmountMerchantCurrency>'. $args['amount'] .'</AmountMerchantCurrency>';
		$xml.=        '<MerchantCurrency>USD</MerchantCurrency>';
		$xml.=        '<ExpirationTime>'. $expireTime .'</ExpirationTime>';
		$xml.=        '<SiteAddress>'. $this->_settings['host'] .'</SiteAddress>';
		$xml.=        '<Description>'. $description .'</Description>';
		$xml.=        '<ProductName>'. $product .'</ProductName>';
		$xml.=        '<SuccessURL>'. $this->_settings['successurl'] .'</SuccessURL>';
		$xml.=        '<FailureURL>'. $this->_settings['failureurl'] .'</FailureURL>';
		$xml.=        '<PostbackURL>'. $this->_settings['postbackurl'] .'</PostbackURL>';
		$xml.=        '<Buyer>';
		$xml.=            '<FirstName>'. $user->name .'</FirstName>';
		$xml.=            '<LastName>'. $user->name .'</LastName>';
		$xml.=            '<AccountID>'. $account->id .'</AccountID>';
		$xml.=        '</Buyer>';
	    $xml.=    '</Order>';
		$xml.=    '<Param3>Int</Param3>';
		$xml.= '</Body>';
		$xml.= '</Message>';
        return $xml;
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
            'Referer: '.$this->_host,
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
        $this->_responseDom->loadXML($response);
        $this->getError();
        return $this->getResponse();
	}
    public function signature($time){
        return strtoupper(hash('sha512',strtoupper($this->_settings['UserID']).strtoupper(hash('sha512',$this->_settings['password'])).strtoupper($time)));
    }
    protected function getElementByTagName($tagname) {
        $node = $this->_responseDom->getElementsByTagName($tagname);
		return ($node->length)?$node->item(0)->nodeValue:false;
	}
    protected function getError(){
        if($this->getElementByTagName('Error')){
			$errId = $this->getElementByTagName( 'ErrorID');
			$errText = $this->getElementByTagName( 'ErrorMessage');
			if($errId == '003') $answer['error'] = 'Проблема с полями // '.$errText;
			if($errId == '004') $answer['error'] = 'Метод не поддерживается // '.$errText;
			elseif($errId == '005' or $errId == '008') $answer['error'] = 'Попробуйте позже // '.$errText;
			elseif($errId == '007') $answer['error'] = 'Параметры в order неверны // '.$errText; // получить тело ошибки и вывести
			elseif($errId == '011') $answer['error'] = 'Недостаточно средств // '.$errText;
			elseif($errId == '012') $answer['error'] = 'Операция не поддерживается // '.$errText;
			elseif($errId == '015') $answer['error'] = 'Превышен максимум запросов // '.$errText;
			else $errText = $this->_response;
			throw new PayException($errText,$errId);
		}
    }
    protected function getResponse(){
        $res = [];
        if($this->getElementByTagName( 'Status') == 'created'){
			// сохраняем в базу
			$res['OrderID'] = $this->getElementByTagName( 'OrderID');
			$res['ReferenceID'] = $this->getElementByTagName( 'ReferenceID');
			$res['AmountMerchantCurrency'] = $this->getElementByTagName( 'AmountMerchantCurrency');
			$res['MerchantCurrency'] = $this->getElementByTagName( 'MerchantCurrency');
			$res['amount'] = $this->getElementByTagName( 'AmountBuyerCurrency');
			$res['BuyerCurrency'] = $this->getElementByTagName( 'BuyerCurrency');
			$res['RedirectURL'] = $this->getElementByTagName( 'RedirectURL');
		}
        else throw new PayException('Неизвестная ошибка ');
        return $res;
    }
};
?>
