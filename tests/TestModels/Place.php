<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Juanparati\LaraGeos\Casts\RegionCast;
use Juanparati\LaraGeos\Traits\HasSpatial;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Class Place
 *
 * @property Polygon area
 */
class Place extends Model
{
    use HasSpatial;

    protected $fillable = [
        'area',
    ];

    protected $casts = [
        'area' => RegionCast::class,
    ];
}
