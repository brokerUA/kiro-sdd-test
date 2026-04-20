<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetTokenModel extends Model
{
    protected $table = 'password_reset_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];
}
