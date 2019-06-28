<?php namespace Vsb\Traits;
trait UserTransactions{
    public function deposits(){
        return $this->hasMany('Vsb\Model\Transaction')->whereIn('type',['deposit'])->where('code','200')->orderBy('id')->limit(1);
    }
    public function transactions(){
        return $this->hasMany('Vsb\Model\Transaction')->whereIn('type',['deposit','withdraw'])->where('code','200');
    }
    public function operations(){
        return $this->hasMany('Vsb\Model\Transaction')->where('code','200');
    }
    public function invoices(){
        return $this->hasMany('Vsb\Model\TransactionInvoice')->where('error',0);
    }
    public function withdrawals(){
        return $this->hasMany('Vsb\Model\TransactionWithdrawal');
    }
}
?>
