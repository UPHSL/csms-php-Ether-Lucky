<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request for a community service made by a Resident.
 *
 * The owning Resident is referenced only through resident_id; Resident
 * information itself remains in the Resident domain. date_requested is held
 * as a YYYY-MM-DD date string.
 */
class ServiceRequest extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resident_id',
        'service_type',
        'description',
        'date_requested',
        'status',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Pending',
    ];
}
