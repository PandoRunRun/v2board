<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OttRenewal extends Model
{
    protected $table = 'v2_ott_renewal';
    protected $fillable = [
        'user_id',
        'account_id',
        'target_year',
        'price',
        'is_paid',
        'sub_account_id',
        'sub_account_pin'
    ];
    protected $casts = [
        'is_paid' => 'boolean',
        'price' => 'decimal:2',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function account()
    {
        return $this->belongsTo(OttAccount::class, 'account_id');
    }
}
