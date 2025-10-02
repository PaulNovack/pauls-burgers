<?php

// app/Models/Topping.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topping extends Model
{
    protected $fillable = ['name','allowed_for','synonyms'];
    protected $casts = [
        'allowed_for' => 'array',
        'synonyms'    => 'array',
    ];

    // If you already have relation to items:
    // public function items() { return $this->belongsToMany(Item::class); }
}
