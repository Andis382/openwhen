<?php

namespace App\Models;

use App\Routing\GeoPoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'country', 'locale', 'timezone', 'currency', 'depot_name', 'depot_lat', 'depot_lng'];

    protected function casts(): array
    {
        return [
            'depot_lat' => 'float',
            'depot_lng' => 'float',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Where routes start and end. Without one, the first shop of the trip stands in. */
    public function depot(): ?GeoPoint
    {
        return $this->depot_lat === null || $this->depot_lng === null ? null : new GeoPoint($this->depot_lat, $this->depot_lng);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'country' => $this->country,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'depot' => $this->depot() === null ? null : [
                'name' => $this->depot_name,
                'lat' => $this->depot_lat,
                'lng' => $this->depot_lng,
            ],
        ];
    }
}
