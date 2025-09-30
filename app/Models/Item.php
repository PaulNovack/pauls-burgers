<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    // primary key is provided by your catalog
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id','tenant_id','name','type','category','size','price',
    ];

    public function toppings() {
        return $this->belongsToMany(Topping::class);
    }
}
