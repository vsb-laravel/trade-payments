<?php namespace Vsb\payments;
class PObject{
    protected $_raw=[];
    public function __construct($a=[]){
        if(is_array($a) && count($a)) $this->_raw = array_merge($this->_raw,$a);
        elseif(is_object($a))$this->_raw = array_merge($this->_raw,json_decode($a,true));
    }
    public function __isset($n){
        return isset($this->_raw[$n]);
    }
    public function __unset($n){
        unset($this->_raw[$n]);
    }
    public function __get($n){
        return $this->__isset($n)?$this->_raw[$n]:false;
    }
    public function __set($n,$v){
        $this->_raw[$n]=$v;
    }
    public function toArray(){
        return $this->_raw;
    }
    public function toJson(){
        return json_encode($this->_raw,JSON_UNESCAPED_UNICODE);
    }
    public function __toString(){
        return $this->toJson();
    }
    public function toUri(){
        return http_build_query($this->_raw);
    }
    public function toForm(){
        $f='';
        foreach ($this->_raw as $key => $value) {
            $f.='<input name="'.$key.'" value="'.$value.'" type="hidden"/>';
        }
        return $f;
    }
    public function toHtml(){
        return (isset($this->_raw['view']))?view($this->_raw['view'],["page"=>$this->_raw]):'';
    }
};
?>
