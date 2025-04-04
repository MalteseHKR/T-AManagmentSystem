<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'path'
    ];

    /**
     * Get the employee that owns the image.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}