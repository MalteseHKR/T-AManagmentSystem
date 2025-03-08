<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_surname',
        'user_title',
        'user_phone',
        'user_email',
        'user_dob',
        'user_job_start',
        'user_active',
        'user_department',
    ];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
