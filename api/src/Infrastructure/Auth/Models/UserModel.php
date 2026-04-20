<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'password_hash',
        'created_at',
        'updated_at',
    ];
}
