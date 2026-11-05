<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemType extends Model
{
    protected $table = 'system_types';


    protected $fillable = [
        'name'
    ];

    public static function getTypes()
    {
        $types = SystemType::select('id', 'name')->get();

        return $types;
    }

}
