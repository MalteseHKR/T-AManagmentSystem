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
        'login_attampts',
        'last_login_attampt',
    ];

    protected $attributes = [
        'login_attampts' => 0
    ];

    public function userInformation()
    {
        return $this->belongsTo(UserInformation::class, 'user_id', 'user_id');
    }

    public $timestamps = false;
}
