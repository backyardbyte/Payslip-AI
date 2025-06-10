<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Koperasi extends Model
{
    use HasFactory;

    protected $table = 'koperasi';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];
} 