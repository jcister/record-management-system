<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'agency_name',
        'telephone',
        'address',
        'regional_director_name',
        'regional_director_designation',
    ];

    /**
     * Get the singleton system configuration instance.
     * Creates one if it doesn't exist.
     */
    public static function getInstance(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'agency_name' => 'Commission on Higher Education Regional Office XI',
            'address' => 'Loyola Street, Barrio Obrero, Davao City, 8000 Davao del Sur',
            'telephone' => '',
            'regional_director_name' => 'Maricar R. Casquejo, Ph.D, CESO III',
            'regional_director_designation' => 'Director IV',
        ]);
    }
}
