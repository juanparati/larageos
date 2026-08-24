<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests\TestModels;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Juanparati\LaraGeos\Casts\RegionCast;
use Juanparati\LaraGeos\Traits\HasGeoSpatial;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Class Place
 *
 * @method void whereContains(Builder $query, string $column, Point|Polygon $geometry)
 * @method void whereIntersects(Builder $query, string $column, Point|Polygon $geometry)
 *
 * @property Polygon area
 */
class Place extends Model
{
    use HasGeoSpatial;

    protected $fillable = [
        'area',
    ];

    protected $casts = [
        'area' => RegionCast::class,
    ];
}
