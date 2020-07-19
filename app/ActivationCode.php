<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ActivationCode extends Model
{
    protected $table = 'user_activation_codes';

    protected $fillable = ['email', 'code'];
}
