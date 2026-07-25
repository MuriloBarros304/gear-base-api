<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    // Arrays simples e limpos. Como esta entidade não tem dependências diretas iniciais, a classe fica bem enxuta.
    protected $fillable = [
        'name',             // string
        'serial_number',    // string
        'status',           // string (ex: 'available', 'in_use', 'maintenance')
    ];
}
