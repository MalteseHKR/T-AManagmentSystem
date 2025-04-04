<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    use HasFactory;

    protected $table = 'login';

    protected $fillable = [
        'email',
        'user_login_pass',
        'user_id',
        'login_attempts',
    ];

    /**
     * Get the user that owns the login.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}