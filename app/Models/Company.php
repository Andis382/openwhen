<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One distributor: three to thirty vans.
 *
 * There is deliberately no table anywhere in this schema that could join one
 * company's visits to another's. A shared open-hours graph across distributors
 * is an obvious later product and an obvious later temptation, and the moment
 * it exists the thing being shared stops being opening hours and starts being
 * "who calls on which shop, how often". That belongs to the distributor.
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city', 'timezone'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function drivers(): HasMany
    {
        return $this->users()->where('role', User::DRIVER);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class)->orderBy('name');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function today(): CarbonInterface
    {
        return now($this->timezone ?: config('app.timezone'))->startOfDay();
    }
}
