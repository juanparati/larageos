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
 * Distance scopes for models with spatial point columns.
 *
 * Distances are in meters on every driver:
 * - MySQL 8+: geodesic ST_Distance on geographic SRIDs (ellipsoid).
 * - MariaDB: ST_Distance_Sphere (spherical approximation, ~0.5% off ellipsoid
 *   results; POINT columns only — MariaDB cannot measure polygon distances).
 * - PostgreSQL/PostGIS: ST_Distance, meters on geography columns (ellipsoid);
 *   geometry columns return SRS units instead.
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
     * Filter rows within the given distance in meters (see the trait docblock
     * for per-driver semantics).
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

        // MariaDB's ST_Distance is Cartesian (degrees for lat/lng data);
        // ST_Distance_Sphere returns meters like the other drivers.
        $function = $driver === 'mariadb' ? 'ST_Distance_Sphere' : 'ST_Distance';

        return ["{$function}({$wrapped}, {$pointSql})", $bindings];
    }
}
