<?php namespace Vsb\Traits;
trait UserAccounts{
    public function accounts(){
        return $this->hasMany('Vsb\Model\Account','user_id');
    }
    public function getBalanceAttribute(){
        $ret =  $this->accounts()->where('type','real')->where('status','open')->sum('amount');

        return floatval($ret);
    }
}
?>
