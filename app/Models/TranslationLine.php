<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationLine extends Model
{
    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
    ];
}
