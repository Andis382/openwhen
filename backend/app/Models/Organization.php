<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'country', 'locale', 'timezone', 'currency'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
        ];
    }
}
