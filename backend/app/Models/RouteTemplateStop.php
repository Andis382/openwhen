<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One shop's place in a route template. Reached only through its (tenant-scoped) template. */
class RouteTemplateStop extends Model
{
    public $timestamps = false;

    protected $fillable = ['route_template_id', 'shop_id', 'position'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
