<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    use HasFactory;

    protected $table = 'login';

    protected $primaryKey = 'user_login_id';

    protected $fillable = [
        'email',
        'user_login_pass',
        'user_id',
        'login_attempts',
        'last_login_attempt',
    ];

    public function userInformation()
    {
        return $this->belongsTo(UserInformation::class, 'user_id');
    }
}
