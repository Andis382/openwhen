<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'area', 'lat', 'lng', 'phone', 'note', 'active'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'active' => 'bool'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    public function label(): string
    {
        return $this->code ? $this->name.' · '.$this->code : $this->name;
    }

    public function location(): string
    {
        return trim(implode(' · ', array_filter([$this->address, $this->area])));
    }

    public function mapLink(): ?string
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return 'https://www.openstreetmap.org/?mlat='.$this->lat.'&mlon='.$this->lng
            .'#map=18/'.$this->lat.'/'.$this->lng;
    }
}
