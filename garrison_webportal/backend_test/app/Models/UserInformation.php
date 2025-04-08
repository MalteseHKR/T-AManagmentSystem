<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'user_information';
    
    // Specify the correct primary key
    protected $primaryKey = 'user_id';
    
    // If the primary key is not auto-incrementing, you should set this to false
    public $incrementing = true;
    
    public $timestamps = false;

    // Update fillable array to include the new foreign keys
    protected $fillable = [
        'user_name',
        'user_surname',
        'user_phone',
        'user_email',
        'user_dob',
        'user_job_start',
        'user_job_end',
        'user_active',
        'department_id',
        'role_id',
    ];

    /**
     * Get the user that owns the user information.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the department associated with the user.
     */
    public function department()
    {
        // Simply return the relationship definition - don't log here
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        // Simply return the relationship definition - don't log here
        return $this->belongsTo(Role::class, 'role_id');
    }
}