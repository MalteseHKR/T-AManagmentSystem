<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'user_information';

    protected $primaryKey = 'user_id';

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

    public $timestamps = false;

    public function logins()
    {
        return $this->hasMany(Login::class, 'user_id');
    }
}
