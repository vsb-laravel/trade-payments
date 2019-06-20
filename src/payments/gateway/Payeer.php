<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
/*
> ID: 591079851
Секретный ключ: Ch4v227zFbF5xhA5
Ключ для шифрования дополнительных параметров: HpTMU6YdB9bKCkf68dR7dzrau3sJrFhTnr2L
*/
class Payeer {
    protected $name = 'payeer';

    protected $_gateUrl = 'https://payeer.com/merchant/';
    protected $_settings = [
        'm_shop' => '591079851',
        'm_key' => 'Ch4v227zFbF5xhA5',
        'm_curr' => 'USD',
        'm_key_addition' => 'HpTMU6YdB9bKCkf68dR7dzrau3sJrFhTnr2L',
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
            $this->_settings['m_shop'] = $merchant->settings['m_shop'];
            $this->_settings['m_key'] = $merchant->settings['m_key'];
            $this->_settings['m_key_addition'] = $merchant->settings['m_key_addition'];
            $this->_settings['m_curr'] = $merchant->settings['m_curr'];
            $this->_gateUrl = $merchant->settings['gate'];
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
    }
    public function success($resp){}
    public function response($resp){
        $success = false;
        $response = false;
        // Отклоняем запросы с IP-адресов, которые не принадлежат Payeer
        // if (in_array($_SERVER['REMOTE_ADDR'], ['185.71.65.92', '185.71.65.189', '149.202.17.210']) && isset($_POST['m_operation_id']) && isset($_POST['m_sign'])) {
        if (isset($_POST['m_operation_id']) && isset($_POST['m_sign'])) {
            $m_key = $this->_settings['m_key'];
            // Формируем массив для генерации подписи
            $arHash = [
                $_POST['m_operation_id'], $_POST['m_operation_ps'], $_POST['m_operation_date'], $_POST['m_operation_pay_date'], $_POST['m_shop'], $_POST['m_orderid'], $_POST['m_amount'], $_POST['m_curr'], $_POST['m_desc'], $_POST['m_status']
            ];
            // Если были переданы дополнительные параметры, то добавляем их в
            if (isset($_POST['m_params'])) {
                $arHash[] = $_POST['m_params'];
            }
            // Добавляем в массив секретный ключ
            $arHash[] = $m_key;
            // Формируем подпись
            $sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));
            // Если подписи совпадают и статус платежа “Выполнен”
            $response = $_POST['m_orderid'].'|error';

            if ($_POST['m_sign'] == $sign_hash && $_POST['m_status'] == 'success') {
                // Здесь можно пометить счет как оплаченный или зачислить денежные средства Вашему клиенту
                // Возвращаем, что платеж был успешно обработан
                $response = $_POST['m_orderid'].'|success';
                $success = true;
            }
        }
        $ret = [
            'success' => $success,
            'description' => $_POST['m_operation_ps'],
            'method' => $_POST['m_operation_ps'],
            'reference' => $_POST['m_operation_id'],
            'orderId' => $_POST['m_orderid'],
            'amount' => $_POST['m_amount'],
            'currency' => $_POST['m_curr'],
            'fee' => '0',
            'answer' => $response
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
            $form = '<form action="'.$this->_gateUrl.'" method="GET">'.$request->toForm().'</form>';
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
        $user = $args['user'];
        $amount = number_format($args['amount'], 2, '.', '');
        $arg = [
            'm_shop' => $this->_settings["m_shop"],
            'm_orderid' => $args['order_id'],
            'm_amount' => $amount,
            'm_curr'=>$this->_settings['m_curr'],
            'm_desc'=>base64_encode('Deposit for #'.$user->id.' '.$user->title),
        ];

        $this->signature($arg);
        Log::debug('PG:Payeer '.json_encode($arg));
        return new Request($arg);
    }
    public function signature(&$args){
        $arHash = [
            $args['m_shop'], $args['m_orderid'], $args['m_amount'], $args['m_curr'] , $args['m_desc']
        ];
        $arParams = [
            'success_url'=>$this->_settings["successurl"],
            'fail_url'=>$this->_settings["failureurl"],
            'status_url'=>$this->_settings["postbackurl"],
            // 'reference' => []
            //'submerchant' => 'mail.com',
        ];
        // Формируем ключ для шифрования
        $key = md5($this->_settings['m_key_addition'].$args['m_orderid']);
        // Шифруем дополнительные параметры
        // $m_params = urlencode(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, json_encode($arParams), MCRYPT_MODE_ECB)));
        // Шифруем дополнительные параметры с помощью AES-256-CBC (для >= PHP 7) //
        // $m_params = urlencode(base64_encode(openssl_encrypt(json_encode($arParams), $args['m_cipher_method'], $key, OPENSSL_RAW_DATA)));
        $iv = openssl_random_pseudo_bytes(16);
        $m_params = urlencode(base64_encode(openssl_encrypt(json_encode($arParams), 'AES-256-CBC', $key, OPENSSL_RAW_DATA,$iv)));
        // Добавляем параметры в массив для формирования подписи
        $arHash[] = $m_params;
        $args['m_params'] = $m_params;
        // Добавляем в массив для формирования подписи секретный ключ
        $arHash[] = $this->_settings['m_key'];
        // Формируем подпись
        $args['m_sign'] = strtoupper(hash('sha256', implode(':', $arHash)));
        $args['m_cipher_method'] = 'AES-256-CBC';
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
