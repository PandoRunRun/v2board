<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OttUser extends Model
{
    protected $table = 'v2_ott_user';
    protected $fillable = [
        'user_id',
        'account_id',
        'sub_account_id',
        'sub_account_pin',
        'expired_at'
    ];
    protected $casts = [
        'expired_at' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function account()
    {
        return $this->belongsTo(OttAccount::class, 'account_id', 'id');
    }
}
