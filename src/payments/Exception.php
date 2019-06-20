<?php namespace Vsb\payments;
class Exception extends \Exception{
    protected $_merchant;
    public function __construct($code,$message=null,$merchant=null){
        $this->_merchant = is_null($merchant)?'common':$merchant;
        $m = is_null($message)?$this->_getMessageByCode($code):$message;
        $m = "[".$this->_merchant."]:".$m;
        parent::__construct($m,$code);
    }
    protected function _getMessageByCode($code){
        switch($this->_merchant){
            case "common":{
                switch($code){

                }
            }break;
        }
        return 'Merchant:'.$this->_merchant."\t".'Unknown payment exception #'.$code;
    }
};
?>
