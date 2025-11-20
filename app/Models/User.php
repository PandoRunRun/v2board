<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'v2_user';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'is_ott' => 'boolean'
    ];

    public function ottAccounts()
    {
        return $this->belongsToMany(OttAccount::class, 'v2_ott_user', 'user_id', 'account_id')
            ->withPivot('expired_at', 'sub_account_id', 'sub_account_pin')
            ->withTimestamps();
    }
}
