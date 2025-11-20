<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OttLog extends Model
{
    protected $table = 'v2_ott_log';

    protected $fillable = [
        'account_id',
        'type',
        'status',
        'message',
        'data'
    ];

    protected $casts = [
        'status' => 'boolean',
        'data' => 'array'
    ];

    public function account()
    {
        return $this->belongsTo(OttAccount::class, 'account_id');
    }
}
