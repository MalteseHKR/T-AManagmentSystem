<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LoginUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'login'; // Custom table name

    protected $primaryKey = 'user_id'; // Primary key used for Auth::id()

    public $timestamps = false; // Your table doesn't have created_at / updated_at

    protected $fillable = [
        'email',
        'user_login_pass',
        'password_reset',
        'user_id'
    ];

    protected $hidden = [
        'user_login_pass',
    ];

    public function getAuthPassword()
    {
        return $this->user_login_pass;
    }
}
