<?php

namespace Vsb\Model;

use Vsb\Crm\Scopes\EnableScope;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected static function boot()
    {
        parent::boot();

        // static::addGlobalScope(new EnableScope);
    }
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currencies';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name','code','image','symbol','unicode','reserve','enabled'
    ];
    protected $hidden = [
        'image'
    ];
    // public function getImageAttribute(){
    //     return $this->image;
    // }
}
