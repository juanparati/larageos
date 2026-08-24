<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Support;

use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Juanparati\LaraGeos\Exceptions\UnsupportedDriverException;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Generates driver-specific spatial SQL. This is the only place that maps
 * database drivers to their geometry function dialects:
 *
 * - mysql (8.0+): geographic SRSs are stored latitude-first, so WKT input and
 *   output must carry the 'axis-order=long-lat' option.
 * - mariadb: plain OGC functions, no axis-order options, Cartesian semantics.
 * - pgsql (PostGIS): ST_MakePoint/ST_SetSRID; x is always longitude.
 */
final class GeometryExpression
{
    /**
     * The geometry's own SRID, falling back to the larageos.default_srid config.
     */
    public static function resolveSrid(Point|Polygon $geometry): int
    {
        return $geometry->getSrid() ?: (int) (config('larageos.default_srid') ?? 0);
    }

    /**
     * Inline ST_GeomFromText() SQL for writing a geometry through an Eloquent
     * cast. The WKT is generated from validated floats, never from raw input.
     *
     * @throws UnsupportedDriverException
     */
    public static function geomFromText(Point|Polygon $geometry, string $driver): string
    {
        self::assertSupported($driver);

        $srid = self::resolveSrid($geometry);

        if ($srid === 0) {
            return "ST_GeomFromText('{$geometry->toWkt()}')";
        }

        return $driver === 'mysql'
            ? "ST_GeomFromText('{$geometry->toWkt()}', {$srid}, 'axis-order=long-lat')"
            : "ST_GeomFromText('{$geometry->toWkt()}', {$srid})";
    }

    /**
     * Parameterized SQL expression producing the given point, for use in scopes.
     *
     * @return array{string, array}  [sql with placeholders, bindings]
     *
     * @throws UnsupportedDriverException
     */
    public static function pointExpression(Point $point, string $driver): array
    {
        $srid = self::resolveSrid($point);

        return match ($driver) {
            'mysql' => ["ST_GeomFromText(?, ?, 'axis-order=long-lat')", [$point->toWkt(), $srid]],
            'mariadb' => ['ST_GeomFromText(?, ?)', [$point->toWkt(), $srid]],
            'pgsql' => ['ST_SetSRID(ST_MakePoint(?, ?), ?)', [$point->getLng(), $point->getLat(), $srid]],
            default => throw UnsupportedDriverException::make($driver),
        };
    }

    /**
     * Inverse of geomFromText(): extracts the WKT and SRID from an Expression
     * previously produced by this class.
     *
     * @return array{string, int|null}  [wkt, srid or null when absent]
     */
    public static function extractWkt(Expression $expression, Grammar $grammar): array
    {
        $sql = (string) $expression->getValue($grammar);

        if (preg_match("/ST_GeomFromText\(\s*'([^']+)'\s*(?:,\s*(\d+))?/", $sql, $matches) !== 1) {
            throw new InvalidArgumentException("The given expression is not a geometry expression: {$sql}");
        }

        return [$matches[1], isset($matches[2]) ? (int) $matches[2] : null];
    }

    private static function assertSupported(string $driver): void
    {
        if (!in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            throw UnsupportedDriverException::make($driver);
        }
    }
}
