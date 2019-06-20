<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use Illuminate\View\View;
class PBSDirectPayment {
    protected $_gateUrl = 'https://co.onlineb2cmall.com/TPInterface';
    protected $_gateUrlCheck = 'https://check.onlinesslmall.com/servlet/NormalCustomerCheck';
    // protected $_gateUrl = 'https://co.onlineb2cmall.com/TestTPInterface';
    // protected $_gateUrlCheck = 'https://check.onlinesslmall.com/servlet/TestCustomerCheck';

    protected $_settings = [
        /*



        MID :20490
Gateway No:20490001 (Visa, block U.S)
Key: zP8440H8

> Gateway No. 20490003 (MC)
Key: d0ZRxNpL
        */
        'merchant_id' => '20490',
        'ps' => [
            'visa'=>[
                'gateway'=>'20490001',
                'merchant_key'=>'zP8440H8'
            ],
            'mc'=>[
                'gateway'=>'20490003',
                'merchant_key'=>'d0ZRxNpL'
            ],
        ],
        'currency' => 'USD',
        'live' => false,
        "language"=>"en",
        "view"=>"app.form.pbs",
        // "view"=>"app.deals",
        'host' => 'http://trade.cryptogriff.com',
        'successurl' => 'http://trade.cryptogriff.com/pay/pbs/success',
        'failureurl' => 'http://trade.cryptogriff.com/pay/pbs/fail',
        'postbackurl' => 'http://trade.cryptogriff.com/pay/pbs/back'
    ];
    /*
    {
        "host"=>"https://trade.forex2crypto.com",
        "merchant_id":"20490",
        "gate":"https://co.onlineb2cmall.com/TestTPInterface",
        "check_gate":"https://co.onlineb2cmall.com/TestTPInterface",
        "VISA_gateway": "20490001",
        "VISA_key": "zP8440H8",
        "MC_gateway": "20490003",
        "MC_key": "d0ZRxNpL"
    }
     */
    protected $_responseDom=false;
    protected $_response='';
    protected $name = 'pbs';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(!is_null($merchant)){
            $host =  $merchant->settings['host'];
            $this->_gateUrl = $merchant->settings['gate'];
            $this->_gateUrl = $merchant->settings['check_gate'];
            $this->_settings['host'] = $host;
            $this->_settings['merchant_id'] = $merchant->settings['merchant_id'];
            $this->_settings['currency'] = $merchant->settings['currency'];
            $this->_settings['ps']['visa']['gateway'] = $merchant->settings['VISA_gateway'];
            $this->_settings['ps']['visa']['merchant_key'] = $merchant->settings['VISA_key'];
            $this->_settings['ps']['mc']['gateway'] = $merchant->settings['MC_gateway'];
            $this->_settings['ps']['mc']['merchant_key'] = $merchant->settings['MC_key'];

            Log::debug("PG ".json_encode($this->_settings)." : ".json_encode($merchant->settings));
        }
        $this->_settings["host"]=$host;
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
    }
    public function response($resp){
        $ret = [
            'success' => (isset($resp['status']) && $resp['status']=='success'),
            'reference' => isset($resp['tradeNo'])?$resp['tradeNo']:'-',
            'orderId' => isset($resp['orderNo'])?$resp['orderNo']:'0',
            'amount' => isset($resp['orderAmount'])?$resp['orderAmount']:'0',
            'currency' => isset($resp['orderCurrency'])?$resp['orderCurrency']:'0',
            'fee' => isset($resp['ac_fee'])?$resp['ac_fee']:'0',
        ];
        return new Response($ret);
    }
    public function sale($args){
        $res = $this->build($args);
        return isset($res['merchant_id'])?[
            "append"=>[
                "view"=>view($this->_settings['view'],["page"=>$res])->render()
            ]
        ]:new Response($res);
    }
    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');

        $user = $args['user'];
        $arg = [
            'merNo' => $this->_settings["merchant_id"],
            'orderNo' => (preg_match('/.+TestTPInterface/',$this->_gateUrl))?"00001":$args["order_id"],
            'orderCurrency'=>$this->_settings['currency'],
            'orderAmount' => $args["amount"],
            'returnUrl'=>$this->_settings['successurl'],
            'paymentMethod'=>'Credit Card',
            'firstName'=>$user->name,
            'lastName'=>$user->surname,
            'ip'=>$args['ip'],
            'issuingBank'=>'Bank name',
            'email'=>$user->email,
            'phone'=>$user->phone,
            'country'=>$user->country[0]->meta_value,
            'city'=>'Town',
            'address'=>'Some street in some Town',
            'zip'=>'123456'
        ];
        if(isset($args['cardNo'])){
            if(!preg_match('/^(4|5)\d+/',$args['cardNo'])) throw new Exception(-1,'Supports only VISA/MasterCard');
            $expire = preg_split('/\//',$args['expire']);
            $ps = preg_match('/^4\d+/',$args['cardNo'])?'visa':'mc';
            $arg['ps'] = $ps;
            $arg['gatewayNo'] = $this->_settings['ps'][$arg['ps']]["gateway"];
            $arg['cardNo'] = preg_replace('/\s/m','',$args['cardNo']);
            $arg["cardExpireYear"] = "20".$expire[1];
            $arg["cardExpireMonth"] = $expire[0];
            $arg["cardSecurityCode"] = $args['cardSecurityCode'];
            $arg['signInfo'] = $this->signature($arg);
            $url = $this->_gateUrl;
            $req = $arg;
            $request = new Request($arg);
            $response = $request->post($url);
            $xmlob = \simplexml_load_string(trim($response));

            $res=[
                "merNo" => (string)$xmlob->merNo,
                "gatewayNo" => (string)$xmlob->gatewayNo,
                "tradeNo" => (string)$xmlob->tradeNo,
                "orderNo" => (string)$xmlob->orderNo,
                "orderAmount" => (string)$xmlob->orderAmount,
                "orderCurrency" => (string)$xmlob->orderCurrency,
                "orderStatus" => (string)$xmlob->orderStatus,
                "orderInfo" => (string)$xmlob->orderInfo,
                "signInfo" => (string)$xmlob->signInfo,
                "riskInfo" => (string)$xmlob->riskInfo
            ];
            $i=0;
            $orderPending = false;
            if(strtolower($res['signInfo']) == hash("sha256",$res["merNo"].$res["gatewayNo"].$res["tradeNo"].$res["orderNo"].$res["orderCurrency"].$res["orderAmount"].$res["orderStatus"].$res["orderInfo"].$this->_settings['ps'][$ps]["merchant_key"])){
                if($res['orderStatus'] == "1") $res['status']='success';
                elseif($res['orderStatus'] == "-1"){//PENDING
                    $orderPending = true;
                    $url = $this->_gateUrlCheck;
                    $req=[
                        "merNo" => $res['merNo'],
                        "gatewayNo" => $res['gatewayNo'],
                        "orderNo" => $res['orderNo'],
                        'signInfo' => hash('sha256',$res['merNo'].$res['gatewayNo'].$res['orderNo'].$this->_settings['ps'][$ps]["merchant_key"])
                    ];
                }
                else {$res['status']='failed';$res['message']=$res['orderInfo'];}
            }else {$res['status']='ignore';}
            while($orderPending){
                $request = new Request($req);
                $response = $request->post($this->_gateUrlCheck);
                Log::debug('pbs trx status response:'.$response);
                // $xmlob = (isset($xmlob->tradeinfo))?$xmlob->tradeinfo:$xmlob;
                $xmlob = \simplexml_load_string(trim($response));
                $resp=[
                    "merNo" => (string)$xmlob->merNo,
                    "gatewayNo" => (string)$xmlob->gatewayNo,
                    "tradeNo" => (string)$xmlob->tradeNo,
                    "orderNo" => (string)$xmlob->orderNo,
                    "queryResult" => (string)$xmlob->queryResult,
                    "orderStatus" => (string)$xmlob->orderStatus,
                ];
                Log::debug('pbs response:',$resp);
                if($resp['queryResult'] == "1"){
                    $res['status']='success';
                    $orderPending = false;
                    break;
                }
                elseif(in_array($resp['queryResult'],["-1","-2"])){//PENDING
                    $orderPending = true;
                    sleep(1);
                }
                else {
                    $errors = [
                        "0" => "failure",
                        "2" => "orders does not exist",
                        "3" => "incoming parameters incomplete ",
                        "4" => "order an excessive number (Search max 100 records each time) ",
                        "5" => "merchants, gateway access error",
                        "6" => "signInfo information error",
                        "7" => "access to the ip error",
                        "999" => "query system error occurred"
                    ];
                    $res['status']='failed';
                    $res['error_code'] = $resp['queryResult'];
                    $res['error'] = isset($errors[$resp['queryResult']])?$errors[$resp['queryResult']]:$errors["999"];
                    $orderPending = false;
                    break;
                }

            }
            Log::debug('pbs gate response:',$res);
            return $res;
        }
        else { // just form
            $arg['merchant_id'] = Merchant::where('name','pbs')->first()->id;
            return $arg;
        }
    }
    public function signature($args){
        // $signInfo=sha256(merNo+gatewayNo+orderNo+orderCurrency+orderAmount+firstName+lastName+cardNo+cardExpireYear+cardExpireMonth+cardSecurityCode+email+signkey);
        return  hash('sha256',
                $args["merNo"]
                .$args["gatewayNo"]
                .$args["orderNo"]
                .$this->_settings["currency"]
                .$args["orderAmount"]
                .$args["firstName"]
                .$args["lastName"]
                .$args["cardNo"]
                .$args["cardExpireYear"]
                .$args["cardExpireMonth"]
                .$args["cardSecurityCode"]
                .$args["email"]
                .$this->_settings['ps'][$args['ps']]["merchant_key"]
            );
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'pbs', 'PSB Direct Payment', '1');
?>
