<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    protected $table = 'request_types';

    public function typeName()
    {
        return $this->hasOne(SystemType::class, 'type_id');
    }
}
