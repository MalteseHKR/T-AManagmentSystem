<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'thermal_camera';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'device_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [

    ];

    /**
     * Get the log entries for this device.
     */
    public function logEntries()
    {
        return $this->hasMany(LogInformation::class, 'device_id', 'device_id');
    }
}