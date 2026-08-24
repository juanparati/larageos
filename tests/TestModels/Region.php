<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests\TestModels;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Juanparati\LaraGeos\Casts\RegionCast;
use Juanparati\LaraGeos\Casts\LocationCast;
use Juanparati\LaraGeos\Traits\HasGeoSpatial;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Class Region
 *
 * @method void selectDistanceTo(Builder $query, string $column, Point $point)
 * @method void orderByDistanceTo(Builder $query, string $column, Point $point, string $direction = 'asc')
 * @method void withinDistanceTo(Builder $query, string $column, Point $point, int $distance)
 * @method void whereContains(Builder $query, string $column, Point|Polygon $geometry)
 * @method void whereWithin(Builder $query, string $column, Polygon $polygon)
 * @method void whereIntersects(Builder $query, string $column, Point|Polygon $geometry)
 *
 * @property Point location
 * @property Polygon area
 */
class Region extends Model
{
    use HasGeoSpatial;

    protected $fillable = [
        'location',
        'area',
    ];

    protected $casts = [
        'location' => LocationCast::class,
        'area'     => RegionCast::class,
    ];
}
