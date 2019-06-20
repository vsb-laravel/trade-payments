<?php namespace Vsb\payments\gateway;
use Log;
use App\User;
use App\Merchant;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;
use cryptofx\payments\Payment;
/*
 ID мерчанта: 1411563
 ключ: C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU
 {
    "gate":"https://api.fondy.eu/api/checkout/redirect/",
    "merchant_id":"1411563",
    "password":"C4MomYRdZq5HTqhoqp4YiTj4EfE8aLrU",
    "currency":"USD"
}
*/
class StartAJob extends Payment{
    protected $name = 'startajob';

    protected $_gateUrl = '';
    protected $_settings = [
        'successurl' => '',
        'failureurl' => '',
        'postbackurl' => '',

        'EmployeeIdentification'=>'',
        'JobTitle'=>'',
        'JobDescription'=>'',
        // 'DesiredSalary'=>'',
        'CurrencyISO'=>'USD',
        'JobCategoryID'=>'',
        'SkillsIDs'=>'',
        'EmploymentType'=>'',
        'CompleteWithin'=>'',
        // 'ReturnURL'=>'',
        // 'CustomField1'=>'',
        // 'CustomField2'=>'',
        // 'CustomField3'=>'',

    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        $host = 'https://'.$_SERVER['SERVER_NAME'];
        if(!is_null($merchant)){
            $this->_gateUrl = $merchant->settings['gate'];
            $this->_settings['EmployeeIdentification'] = isset($merchant->settings['EmployeeIdentification'])?$merchant->settings['EmployeeIdentification']:$this->_settings['EmployeeIdentification'];
            $this->_settings['JobTitle'] = isset($merchant->settings['JobTitle'])?$merchant->settings['JobTitle']:$this->_settings['JobTitle'];
            $this->_settings['JobDescription'] = isset($merchant->settings['JobDescription'])?$merchant->settings['JobDescription']:$this->_settings['JobDescription'];
            $this->_settings['CurrencyISO'] = isset($merchant->settings['CurrencyISO'])?$merchant->settings['CurrencyISO']:$this->_settings['CurrencyISO'];
            $this->_settings['JobCategoryID'] = isset($merchant->settings['JobCategoryID'])?$merchant->settings['JobCategoryID']:$this->_settings['JobCategoryID'];
            $this->_settings['SkillsIDs'] = isset($merchant->settings['SkillsIDs'])?$merchant->settings['SkillsIDs']:$this->_settings['SkillsIDs'];
            $this->_settings['EmploymentType'] = isset($merchant->settings['EmploymentType'])?$merchant->settings['EmploymentType']:$this->_settings['EmploymentType'];
            $this->_settings['CompleteWithin'] = isset($merchant->settings['CompleteWithin'])?$merchant->settings['CompleteWithin']:$this->_settings['CompleteWithin'];

            $this->_gateUrl = isset($merchant->settings['gate'])?$merchant->settings['gate']:$this->_gateUrl;
        }
        $this->_settings["successurl"]=$host.'/pay/'.$this->name.'/success';
        $this->_settings["failureurl"]=$host.'/pay/'.$this->name.'/fail';
        $this->_settings["postbackurl"]=$host.'/pay/'.$this->name.'/back';
    }
    public function success($resp){}
    public function response($resp){
        $success = false;
        $response = false;

        /*
        response_status	string(50)	always returns failure	failure
        error_code	integer(4)	Response decline code. Supported values see Response codes
        error_message	string(1024)	Response code description. See Response codes
        */
        $orderId = $this->getPostField('custom1',false);
        // if($orderId===false)$orderId = $this->getPostField('CustomField1',false);
        $success = ($orderId!==false)?true:false;
        $ret = [
            'success' => $success,
            'description' =>  '',
            'method' => 'creditcard',
            'reference' => $this->getPostField('JobId'),
            'orderId' => $orderId,
            'amount' => $this->getPostField('DesiredSalaryNet',0),
            'currency' => $this->getPostField('CurrencyISO'),
            'fee' => floatval($this->getPostField('DesiredSalaryGross',0)) - floatval($this->getPostField('DesiredSalaryNet',0)),
            'answer' => $resp
        ];
        return new Response($ret);
    }
    public function fail(){}
    public function back(){}

    public function build($args){
        // checks
        if(!isset($args['amount'])) throw new Exception(-1,'No amount assigned');
        if(!isset($args['user'])) throw new Exception(-1,'No user assigned');
        $user = $args['user'];
        $amount = intval($args['amount']);

        $arg = [
            'EmployeeIdentification'=>$this->_settings['EmployeeIdentification'],
            'JobTitle'=>$this->_settings['JobTitle'],
            'JobDescription'=>$this->_settings['JobDescription'],
            'DesiredSalary'=>$amount,
            'CurrencyISO'=>$this->_settings['CurrencyISO'],
            'JobCategoryID'=>$this->_settings['JobCategoryID'],
            'SkillsIDs[]'=>$this->_settings['SkillsIDs'],
            'EmploymentType'=>$this->_settings['EmploymentType'],
            'CompleteWithin'=>$this->_settings['CompleteWithin'],
            'ReturnURL'=>$this->_settings["successurl"],
            'CustomField1'=>$args['order_id'],
            // 'CustomField2'=>'',
            // 'CustomField3'=>'',
        ];

        $this->signature($arg);
        Log::debug('PG:StartAJob '.json_encode($arg));
        return new Request($arg);
    }
    public function signature(&$args){
        return ;
    }
    protected function getPostField($f,$def=''){
        return isset($_POST[$f])?$_POST[$f]:$def;
    }
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
            $response = [
                "redirect"=>[
                    "form"=>$form
                ]
            ];
        }
        return $response;
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
/*
Пример HTML поля можно сделать style="display: none"

ReturnURL - это параметр куда будет переадресован покупатель после оплаты (ваш сайт или страница с информацией ...)

Так же Вы можете установить call back URL это адрес будет вызван тогда когда клиент оплатит.

Так же в форме можно указать параметры с которыми будет происходить вызов CustomField1 , CustomField2, CustomField3.


То есть вы можете на своем сайте генерировать уникальную форму для каждого клиента.
*/
?>
