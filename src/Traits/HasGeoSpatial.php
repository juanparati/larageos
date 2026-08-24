<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Juanparati\LaraGeos\Casts\Contracts\LocationCastContract;
use Juanparati\LaraGeos\Casts\Contracts\RegionCastContract;
use Juanparati\LaraGeos\Support\GeometryExpression;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Distance and spatial predicate scopes for models with spatial columns.
 *
 * Distances are in meters on every driver:
 * - MySQL 8+: geodesic ST_Distance on geographic SRIDs (ellipsoid).
 * - MariaDB: ST_Distance_Sphere (spherical approximation, ~0.5% off ellipsoid
 *   results; POINT columns only — MariaDB cannot measure polygon distances).
 * - PostgreSQL/PostGIS: ST_Distance, meters on geography columns (ellipsoid);
 *   geometry columns return SRS units instead.
 *
 * Predicates (whereContains/whereWithin/whereIntersects) evaluate edges
 * geodesically on MySQL geographic SRIDs and PostGIS geography columns, but
 * as straight lines in coordinate space on MariaDB.
 */
trait HasGeoSpatial
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

    /**
     * Filter rows whose geometry column spatially contains the given geometry
     * (typically: polygon column contains a point).
     */
    public function scopeWhereContains(Builder $query, string $column, Point|Polygon $geometry): void
    {
        [$sql, $bindings] = $this->spatialRelationSql($query, $column, $geometry, relation: 'contains');

        $query->whereRaw($sql, $bindings);
    }

    /**
     * Filter rows whose geometry column lies inside the given polygon
     * (typically: point column within an area).
     */
    public function scopeWhereWithin(Builder $query, string $column, Polygon $polygon): void
    {
        [$sql, $bindings] = $this->spatialRelationSql($query, $column, $polygon, relation: 'within');

        $query->whereRaw($sql, $bindings);
    }

    public function scopeWhereIntersects(Builder $query, string $column, Point|Polygon $geometry): void
    {
        [$sql, $bindings] = $this->spatialRelationSql($query, $column, $geometry, relation: 'intersects');

        $query->whereRaw($sql, $bindings);
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

    /**
     * @return array{string, array}  [sql with placeholders, bindings]
     */
    private function spatialRelationSql(Builder $query, string $column, Point|Polygon $geometry, string $relation): array
    {
        $driver = $query->getConnection()->getDriverName();
        $wrapped = $query->getGrammar()->wrap($column);

        [$geometrySql, $bindings] = GeometryExpression::geometryExpression($geometry, $driver);

        // PostGIS geography columns lack ST_Contains/ST_Within; ST_Covers and
        // ST_CoveredBy are the geography-capable equivalents. They also count
        // boundary points as inside, unlike Contains/Within.
        $function = match ($relation) {
            'contains' => $driver === 'pgsql' ? 'ST_Covers' : 'ST_Contains',
            'within' => $driver === 'pgsql' ? 'ST_CoveredBy' : 'ST_Within',
            'intersects' => 'ST_Intersects',
        };

        return ["{$function}({$wrapped}, {$geometrySql})", $bindings];
    }
}
