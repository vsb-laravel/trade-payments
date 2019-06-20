<?php namespace Vsb\payments\gateway;
use Log;
use Mail;
use App\User;
use App\Merchant;
use App\Mail\XcpInvite as XcpInviteMail;
use cryptofx\payments\Exception;
use cryptofx\payments\Request;
use cryptofx\payments\Response;

class Xcp {
    protected $name = 'xcp';
    // INSERT INTO `merchants` (`name`, `title`, `enabled`, `class`, `settings`, `default`) VALUES ('xcp', 'XCP', '0', 'cryptofx\\payments\\gateway\\Xcp', '{"link":"","username":"","password":""}', '0');

    protected $_settings = [
        'link'=>'https://pay.xchangepro.net/?m=5c05f33c-8c26-11e9-9e57-4231c39084ce',
        'username'=>'',
        'password' => ''
    ];
    protected $_responseDom=false;
    protected $_response='';
    public function __construct($a=[]){
        $this->_settings = array_merge($this->_settings,$a);
        $merchant = Merchant::where('name',$this->name)->first();
        if(!is_null($merchant)){
            $this->_settings['link'] = isset($merchant->settings['link'])?$merchant->settings['link']: $this->_settings['link'];
            $this->_settings['username'] = isset($merchant->settings['username'])?$merchant->settings['username']:env('XCP_MAIL_USERNAME');
            $this->_settings['password'] =isset($merchant->settings['password'])? $merchant->settings['password']:env('XCP_MAIL_PASSWORD');
        }
    }
    public function sale($args,$format='form'){
        $user = $args['user'];
        try{
            // Backup your default mailer
            $backup = Mail::getSwiftMailer();
            $transport = new \Swift_SmtpTransport(env('XCP_MAIL_HOST'),env('XCP_MAIL_PORT'),env('XCP_MAIL_ENCRYPTION'));
            $transport->setUsername($this->_settings['username']);
            $transport->setPassword($this->_settings['password']);
            // Any other mailer configuration stuff needed...
            $outlook = new \Swift_Mailer($transport);
            // Set the mailer as outlook
            Mail::setSwiftMailer($outlook);
            //Sending mail
            Mail::to($user)->send( new XcpInviteMail($this->_settings['link'],$this->_settings['username']) );
            // subject(env('XCP_MAIL_FROM_NAME'))
            // Set back
            Mail::setSwiftMailer($backup);
        }
        catch(\Exception $e){
            Log::error($e);
            return [
                "success"=>false,
                "script"=>'alert("'.trans('messages.xcp.failed_response',['email'=>$user->email]).'")'
            ];
        }
        return [
            "success"=>true,
            "script"=>'alert("'.trans('messages.xcp.success_response',['email'=>$user->email]).'")'
        ];
    }
    public function build($args){

    }
    public function success($resp){}
    public function response($respStr){

    }
    public function fail(){}
    public function back(){}
    public function signature($args,$prod){
        return '';
    }
};
//INSERT INTO `merchants` (`id`, `name`, `title`, `enabled`) VALUES (NULL, 'ikajo', 'WebPayments', '1');
?>
