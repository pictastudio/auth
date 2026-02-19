<?php

namespace PictaStudio\Auth\Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PictaStudio\Auth\Concerns\HasAuthFeatures;

class User extends Authenticatable
{
    use HasAuthFeatures;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
