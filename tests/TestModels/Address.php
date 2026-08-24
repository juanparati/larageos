<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests\TestModels;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Juanparati\LaraGeos\Casts\LocationCast;
use Juanparati\LaraGeos\Traits\HasSpatial;
use Juanparati\LaraGeos\Types\Point;

/**
 * Class Address
 *
 * @method void selectDistanceTo(Builder $query, string $column, Point $point)
 * @method void orderByDistanceTo(Builder $query, string $column, Point $point, string $direction = 'asc')
 * @method void withinDistanceTo(Builder $query, string $column, Point $point, int $distance)
 *
 * @property Point location
 */
class Address extends Model
{
    use HasSpatial;

    protected $fillable = [
        'location',
    ];

    protected $casts = [
        'location' => LocationCast::class,
    ];
}
