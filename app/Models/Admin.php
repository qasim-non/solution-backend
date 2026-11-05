<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'username',
        'password',
    ];

    public static function getAdmin($info)
    {
        $admin = Admin::where('username', $info['username'])->first();

        return $admin;
    }
}
