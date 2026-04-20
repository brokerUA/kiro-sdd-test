<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class SessionModel extends Model
{
    protected $table = 'sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'last_activity_at',
        'expires_at',
        'created_at',
    ];
}
