<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accounts';
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
        'status','currency_id','user_id','amount','type'
    ];
    /**
     * Scope a query to only include popular users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUser($query,User $user)
    {
        return $query->where('user_id', '=', $user->id);
    }
    public function user(){
        return $this->belongsTo('Vsb\Crm\User');
    }
    public function currency(){
        return $this->belongsTo('Vsb\Crm\Currency');
    }
    public function trades(){
        return $this->hasMany('Vsb\Crm\Deal');
    }
}
