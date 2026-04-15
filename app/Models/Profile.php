<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';
    public $incrementing = false; // Disable auto-incrementing
    protected $keyType = 'string'; // Set key type to string for UUID
    public $timestamps = false; // Disable timestamps if not using created_at and updated_at

    protected $fillable = [
        'id',
        'name',
        'gender',
        'gender_probability',
        'sample_size',
        'age',
        'age_group',
        'country_id',
        'country_probability',
        'created_at'
    ];
}
