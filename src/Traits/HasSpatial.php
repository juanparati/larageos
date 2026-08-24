<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Juanparati\LaraGeos\Casts\Contracts\LocationCastContract;
use Juanparati\LaraGeos\Casts\Contracts\RegionCastContract;
use Juanparati\LaraGeos\Support\GeometryExpression;
use Juanparati\LaraGeos\Types\Point;

/**
 * Distance scopes for models with spatial columns.
 *
 * ST_Distance units depend on the database:
 * - MySQL 8+: meters for geographic SRIDs (e.g. 4326).
 * - MariaDB: Cartesian units of the coordinate system (degrees for lat/lng data).
 * - PostgreSQL/PostGIS: meters for geography columns, SRS units for geometry columns.
 */
trait HasSpatial
{
    public function scopeSelectDistanceTo(Builder $query, string $column, Point $point): void
    {
        if (is_null($query->getQuery()->columns)) {
            $query->select("{$this->getTable()}.*");
        }

        [$sql, $bindings] = $this->distanceSql($query, $column, $point);

        $query->selectRaw("{$sql} as distance", $bindings);
    }

    /**
     * Filter rows within the given distance, expressed in the driver's
     * ST_Distance unit (see the trait docblock).
     */
    public function scopeWithinDistanceTo(Builder $query, string $column, Point $point, float $distance): void
    {
        [$sql, $bindings] = $this->distanceSql($query, $column, $point);

        $query->whereRaw("{$sql} <= ?", [...$bindings, $distance]);
    }

    public function scopeOrderByDistanceTo(Builder $query, string $column, Point $point, string $direction = 'asc'): void
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        [$sql, $bindings] = $this->distanceSql($query, $column, $point);

        $query->orderByRaw("{$sql} {$direction}", $bindings);
    }

    public function getLocationCastedAttributes(): Collection
    {
        return collect($this->getCasts())->filter(fn ($cast) => is_subclass_of($cast, LocationCastContract::class))->keys();
    }

    public function getRegionCastedAttributes(): Collection
    {
        return collect($this->getCasts())->filter(fn ($cast) => is_subclass_of($cast, RegionCastContract::class))->keys();
    }

    /**
     * @return array{string, array}  [sql with placeholders, bindings]
     */
    private function distanceSql(Builder $query, string $column, Point $point): array
    {
        $driver = $query->getConnection()->getDriverName();
        $wrapped = $query->getGrammar()->wrap($column);

        [$pointSql, $bindings] = GeometryExpression::pointExpression($point, $driver);

        return ["ST_Distance({$wrapped}, {$pointSql})", $bindings];
    }
}
