<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserInformation;  // Add this import
use App\Models\Device;  // Add this import for the Device class

class LogInformation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'log_Information';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'event_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date_time_saved',
        'date_time_event',
        'device_id',
        'user_id',
        'punch_type',
        'photo_url',
        'latitude',
        'longitude',
        'punch_date',
        'punch_time',
    ];

    /**
     * Get the user information associated with this log entry.
     */
    public function userInformation()
    {
        return $this->belongsTo(UserInformation::class, 'user_id', 'user_id');
    }

    /**
     * Get the device associated with this log entry.
     */
    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    /**
     * Get the full URL for the photo
     *
     * @return string|null
     */
    public function getPhotoUrlAttribute()
    {
        if (empty($this->attributes['photo_url'])) {
            return null;
        }

        // Extract just the filename from the path
        $filename = basename($this->attributes['photo_url']);
        
        // Generate a URL to the images.serve route
        return route('images.serve', ['filename' => $filename]);
    }

    /**
     * Check if the log entry has a photo
     *
     * @return bool
     */
    public function hasPhoto()
    {
        return !empty($this->attributes['photo_url']);
    }
}