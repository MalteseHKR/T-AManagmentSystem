<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    
    /**
     * Get the users that have this role.
     */
    public function users()
    {
        return $this->hasMany(UserInformation::class, 'role_id');
    }
}