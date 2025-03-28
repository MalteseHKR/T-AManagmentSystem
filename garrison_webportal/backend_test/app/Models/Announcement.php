<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'content',
        'category', 
        'user_id',
        'author_name',
        'author_job_title',
        'author_department',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that created the announcement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the user information associated with this announcement.
     * This correctly references the user_id field in both tables.
     */
    public function userInfo()
    {
        return $this->belongsTo(UserInformation::class, 'user_id', 'user_id');
    }
}
