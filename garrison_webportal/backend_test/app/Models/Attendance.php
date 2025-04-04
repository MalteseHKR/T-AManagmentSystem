<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'punch_in',
        'punch_out',
        'duration',
        'device_id',
        'punch_type',
        'photo_url',
        'punch_date',
        'punch_time',
        'latitude',
        'longitude',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'notes'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    
    public function getClockInAttribute()
    {
        return $this->attributes['clock_in'] ?? $this->attributes['punch_in'] ?? null;
    }
    
    public function getClockOutAttribute() 
    {
        return $this->attributes['clock_out'] ?? $this->attributes['punch_out'] ?? null;
    }
    
    public function getDateAttribute()
    {
        return $this->attributes['date'] ?? $this->attributes['punch_date'] ?? null;
    }
}