<?php
namespace Vsb\Facades;


use App\Account;
use App\Merchant;
use App\Transaction;
use App\User;

use Vsb\Events\DepositEvent;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Facade;

class PaymentManager extends Facade{
    public function createTransaction($args=false){
        if(!isset($args['user']) && !($args['user'] instanceof \App\User)) throw new \Exception(trans('trade-payments::depends.no_user'));
        list($res,$amount)=[["error"=>"404","message"=>"Deposit failed."],abs(floatval($args['amount']))];
        $account = $args['account'];
        $merchant = $args['merchant'];
        $type = $args['type'];
        $user = $args['user'];
        $uid = isset($args['uid'])?$args['uid']:('trx'.time().$account.$user->id);
        if(!is_null(Transaction::where('uid',$uid)->first())) return null;
        $trx = Transaction::create([
                'type'=>$type,
                'user_id'=>$user->id,
                'account_id'=>$account,
                'amount'=>$amount,
                'merchant_id'=>$merchant,
                'code'=>'0',
                'uid'=>$uid
        ]);
        return $trx;
    }
    public function makeBalance($account,$amount,$type,$merchant=null,$event=true){
        $account->amount=(in_array($type,['deposit','debit','transfer_debit','rollback','return']))?$account->amount+abs($amount):$account->amount-abs($amount);
        if($account->amount<0)throw new \Exception(trans('trade-payments::messages.not_enough_balance'),1);
        if( $type === 'deposit' )$account->user()->update(['deposited'=>1]);
        $account->save();
        if( $event && $type === 'deposit' ){
            $object = json_decode(json_encode([
                'amount'=>$amount,
                'account'=>$account->toArray(),
                'merchant'=>is_null($merchant)?[]:$merchant->toArray()
            ]));
            if(in_array($type,['deposit','debit','transfer_debit'])) event(new DepositEvent($object));
        }
    }
    public function makeTransaction($args=false,$event=true){
        try{
            list($res,$amount)=[["error"=>"404","message"=>"Deposit failed."],abs(floatval($args['amount']))];
            $account = Account::findOrFail($args['account']);
            $merchant = Merchant::findOrFail($args['merchant']);
            $type = $args['type'];
            $trx = null;
            if(isset($args['trx_id'])) $trx = Transaction::find($args['trx_id']);
            if(is_null($trx)) $trx = $this->createTransaction($args); else return false;
            if(!is_null($trx)) {
                $this->makeBalance($account,$amount,$type,$merchant,$event);
                $trx->update(["code"=>"200"]);
                UserHistory::create(['user_id'=>$account->user_id,'type'=>'transaction','object_id'=>(is_null($trx)?null:$trx->id),'object_type'=>'transaction','description'=>json_encode([
                        "trx"=>$trx,
                        "tuid"=>isset($args['uid'])?$args['uid']:null
                    ])]);
            }
            else {
                UserHistory::create(['user_id'=>$account->user_id,'type'=>'transaction','object_id'=>(is_null($trx)?null:$trx->id),'object_type'=>'transaction','description'=>'Transaction exists '.json_encode(["tuid"=>$args['uid']])]);
            }
            return $trx;
        }
        catch(\Exception $e){
            $res = json_decode(json_encode([
                "error"=>$e->getCode(),
                'code'=>'500',
                "message"=>$e->getMessage(),
                "trace"=>$e->getTrace()
            ]));
            return $res;
        }

    }
    public function rollback($args=false){
        $type = $args['type'];
        $account = Account::findOrFail($args['account']);
        $amount=abs(floatval($args['amount']));
        $this->makeBalance($account,$amount,'rollback');
    }
}
