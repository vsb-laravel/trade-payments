<?php namespace Vsb\payments;
use Vsb\payments\Request;
use Vsb\payments\Response;
class Gateway {
    public static function sale($merchant,$rq,$format="default"){
        $_merchants = config('trade-payments.gates');
        if(!isset(self::$_merchants[$merchant])) throw new Exception('-1','Unsupported merchant',$merchant);
        $class = self::$_merchants[$merchant];
        $p = new $class();
        return $p->sale($rq,$format);
    }
    public static function response($merchant,$rq){
        $class = self::$_merchants[$merchant];
        $p = new $class();
        return $p->response($rq);
    }
};
?>
