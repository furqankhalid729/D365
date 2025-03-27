<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'orderId',
        'D365_ID',
        'email',
        'orderName'
    ];
}
