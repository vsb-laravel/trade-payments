<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{

    // protected $appends = ['currency'];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transactions';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'U';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'created_at',
        'type','account_id','amount','merchant_id','code','user_id','error_code','error_text','uid'
    ];

    /*
    $r['merchant'] = Merchant::find($row->merchant_id);unset($r['merchant_id']);
    $r['manager'] = User::find($row->user_id);unset($r['user_id']);
    $acc = Account::find($row->account_id);
    $r["user"] = User::find($acc->user_id);
    $r["currency"] = Currency::find($acc->currency_id);unset($r['account_id']);
    */
    public function events(){return $this->morphMany('Vsb\Model\Event', 'object');}
    public function invoice(){
        return $this->hasOne('Vsb\Model\Invoice');
    }
    public function withdrawal(){
        return $this->hasOne('Vsb\Model\Withdrawal','transaction_id');
    }
    public function merchant(){
        return $this->belongsTo('Vsb\Model\Merchant');
    }
    public function manager(){
        return $this->belongsTo('App\User');
    }
    public function comments(){
        return $this->morphMany('Vsb\Model\Comment', 'object');
    }
    public function user(){
        return $this->belongsTo('App\User');
    }
    public function account(){
        return $this->belongsTo('Vsb\Model\Account');
    }
    public function scopeById($query,$id){
        return ($id == false )?$query:$query->where('id',$id);
    }
    public function scopeByType($query,$f){
        return ($f == false )?$query:$query->where('type',$f);
    }
    public function getCurrencyAttribute(){
        $ret = $this->attributes['currency'] = Currency::find(Account::find($this->account_id)->currency_id);
        return $ret;
    }
    public function setCurrencyAttribute($currency){
        $this->attributes['currency'] = $currency;
    }
}
