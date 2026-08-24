<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests\Concerns;

use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Driver-aware expectations for tests. Each helper spells out the literal SQL
 * per driver in one place, so assertions stay explicit without duplicating
 * whole test cases per database.
 */
trait InteractsWithDriverSql
{
    protected function driver(): string
    {
        return $this->app['db']->connection()->getDriverName();
    }

    protected function wrap(string $column): string
    {
        return $this->app['db']->connection()->getQueryGrammar()->wrap($column);
    }

    /**
     * The distance function the scopes use.
     */
    protected function distanceFnSql(): string
    {
        return $this->driver() === 'mariadb' ? 'ST_Distance_Sphere' : 'ST_Distance';
    }

    /**
     * The placeholder SQL a scope embeds for a point argument.
     */
    protected function pointExprSql(): string
    {
        return match ($this->driver()) {
            'mysql' => "ST_GeomFromText(?, ?, 'axis-order=long-lat')",
            'mariadb' => 'ST_GeomFromText(?, ?)',
            'pgsql' => 'ST_SetSRID(ST_MakePoint(?, ?), ?)',
        };
    }

    /**
     * The bindings a scope produces for a point argument.
     */
    protected function pointExprBindings(Point $point): array
    {
        return match ($this->driver()) {
            'mysql', 'mariadb' => [$point->toWkt(), $point->getSrid()],
            'pgsql' => [$point->getLng(), $point->getLat(), $point->getSrid()],
        };
    }

    /**
     * The literal SQL a cast writes for a geometry.
     */
    protected function geomFromTextSql(Point|Polygon $geometry): string
    {
        $srid = $geometry->getSrid();

        if ($srid === 0) {
            return "ST_GeomFromText('{$geometry->toWkt()}')";
        }

        return match ($this->driver()) {
            'mysql' => "ST_GeomFromText('{$geometry->toWkt()}', {$srid}, 'axis-order=long-lat')",
            'mariadb', 'pgsql' => "ST_GeomFromText('{$geometry->toWkt()}', {$srid})",
        };
    }

    /**
     * A point in the driver's own column storage format (the bytes a raw
     * select would return): MySQL/MariaDB internal SRID-prefixed WKB, or
     * PostGIS hex EWKB. All drivers store x = longitude first.
     */
    protected function storedPointWkb(float $lat, float $lng, int $srid): string
    {
        if ($this->driver() === 'pgsql') {
            return bin2hex("\x01" . pack('V', 0x20000001) . pack('V', $srid) . pack('e', $lng) . pack('e', $lat));
        }

        return pack('V', $srid) . "\x01" . pack('V', 1) . pack('e', $lng) . pack('e', $lat);
    }

    /**
     * A single-ring polygon in the driver's own column storage format.
     *
     * @param  array<array{float, float}>  $ring  [lat, lng] vertices, closed
     */
    protected function storedPolygonWkb(array $ring, int $srid): string
    {
        $body = pack('V', 3) . pack('V', 1) . pack('V', count($ring));

        foreach ($ring as [$lat, $lng]) {
            $body .= pack('e', $lng) . pack('e', $lat);
        }

        if ($this->driver() === 'pgsql') {
            // Replace the plain 4-byte type with the EWKB header (type | SRID flag, then SRID).
            return bin2hex("\x01" . pack('V', 0x20000003) . pack('V', $srid) . substr($body, 4));
        }

        return pack('V', $srid) . "\x01" . $body;
    }

    /**
     * Expected distance range between two points, in meters on every driver
     * (ellipsoidal on MySQL/PostGIS geography, spherical on MariaDB).
     *
     * @return array{float, float}  [min, max]
     */
    protected function expectedDistanceBetween(Point $a, Point $b): array
    {
        $degrees = sqrt(($a->getLat() - $b->getLat()) ** 2 + ($a->getLng() - $b->getLng()) ** 2);

        // Rough geodesic bounds: one degree spans 111.32 km at the equator at
        // most; a 20% margin absorbs latitude compression for nearby points
        // and the sphere-vs-ellipsoid difference.
        $meters = $degrees * 111_320;

        return [$meters * 0.6, $meters * 1.2];
    }
}
