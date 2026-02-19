<?php

namespace PictaStudio\Auth\Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class UserWithoutSanctum extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
