<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{

    use HasApiTokens;

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
