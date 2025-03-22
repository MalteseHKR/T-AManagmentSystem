<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'user_information';

    protected $fillable = [
        'user_name',
        'user_surname',
        'user_title',
        'user_phone',
        'user_email',
        'user_dob',
        'user_job_start',
        'user_job_end',
        'user_active',
        'user_department',
    ];

    /**
     * Get the user that owns the user information.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}