<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OttAccount extends Model
{
    protected $table = 'v2_ott_account';
    protected $fillable = [
        'name',
        'type',
        'username',
        'password',
        'is_active',
        'group_id',
        'has_otp',
        'is_shared_credentials',
        'sender_filter',
        'recipient_filter',
        'subject_regex',
        'otp_validity_minutes',
        'ignore_regex',
        'price_monthly',
        'price_yearly',
        'shared_seats',
        'next_price_yearly',
        'next_shared_seats'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_otp' => 'boolean',
        'is_shared_credentials' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'otp_validity_minutes' => 'integer',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'shared_seats' => 'integer',
        'next_price_yearly' => 'decimal:2',
        'next_shared_seats' => 'integer'
    ];

    public function messages()
    {
        return $this->hasMany(OttMessage::class, 'account_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'v2_ott_user', 'account_id', 'user_id')
            ->withPivot('expired_at', 'sub_account_id', 'sub_account_pin')
            ->withTimestamps();
    }
}
