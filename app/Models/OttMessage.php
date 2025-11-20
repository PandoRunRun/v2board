<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OttMessage extends Model
{
    protected $table = 'v2_ott_message';
    protected $guarded = ['id'];
    protected $casts = [
        'received_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function account()
    {
        return $this->belongsTo(OttAccount::class, 'account_id', 'id');
    }
}
