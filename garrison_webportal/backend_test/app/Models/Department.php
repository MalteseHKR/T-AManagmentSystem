<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    
    protected $table = 'departments';
    protected $primaryKey = 'department_id';
    
    /**
     * Get the users that belong to the department.
     */
    public function users()
    {
        return $this->hasMany(UserInformation::class, 'department_id');
    }
}