<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $fillable = [
        'fsq_id',
        'name',
        'address',
        'lat',
        'lng',
        'category',
        'image_url',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}