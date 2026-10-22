<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSignature extends Model
{
    protected $table = 'form_requests';

    public $timestamps = false;

    protected $guarded = ['id'];
}
