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
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}