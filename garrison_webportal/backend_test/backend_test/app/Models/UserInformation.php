<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'user_information';
    
    // Specify the correct primary key
    protected $primaryKey = 'user_id';
    
    // If the primary key is not auto-incrementing, you should set this to false
    public $incrementing = false;

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
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}