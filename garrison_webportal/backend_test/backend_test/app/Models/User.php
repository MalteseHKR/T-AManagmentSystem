<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'login';
    protected $primaryKey = 'user_login_id'; 
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'user_login_pass',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'user_login_pass',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = ['last_login_at'];

    public function getLastLoginAtAttribute()
    {
        return $this->currentLoginAt ?? now();
    }

    /**
     * Get the password for the user.
     * 
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->user_login_pass;
    }

    /**
     * Get the user information associated with this login.
     */
    public function userInformation()
    {
        return $this->belongsTo(UserInformation::class, 'user_id', 'user_id');
    }

    /**
     * Define the relationship with the Employee model
     */
    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id', 'user_id');
    }

}
