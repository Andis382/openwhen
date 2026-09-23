<?php

namespace App\Models;

use App\Hours\DeclaredHours;
use App\Models\Concerns\BelongsToOrganization;
use App\Routing\GeoPoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer the vans deliver to: where it is, who to ask for, and the hours it claims to keep. */
class Shop extends Model
{
    /** @use HasFactory<\Database\Factories\ShopFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'code', 'name', 'address', 'town', 'lat', 'lng', 'phone', 'contact_name',
        'declared_hours', 'access_notes', 'order_value_cents', 'active',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'declared_hours' => 'array',
            'order_value_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(TripStop::class);
    }

    public function declaredHours(): DeclaredHours
    {
        return DeclaredHours::fromArray($this->declared_hours);
    }

    public function point(): GeoPoint
    {
        return new GeoPoint($this->lat, $this->lng);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'town' => $this->town,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'phone' => $this->phone,
            'contactName' => $this->contact_name,
            'accessNotes' => $this->access_notes,
            'orderValueCents' => $this->order_value_cents,
            'active' => $this->active,
            'declaredHours' => $this->declaredHours()->toArray(),
        ];
    }
}
