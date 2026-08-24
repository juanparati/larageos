<?php

namespace Juanparati\LaraGeos\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Support\WkbParser;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class WkbParserTest extends TestCase
{
    // Captured from MariaDB 11.8: ST_GeomFromText('POINT(39.1234 27.1234)', 4326)
    private const MARIADB_POINT_HEX = 'E61000000101000000C7293A92CB8F43408F537424971F3B40';

    // Captured from MariaDB 11.8: ST_GeomFromText('POLYGON((0.5 0.25,1.5 0.25,1.5 1.25,0.5 0.25))', 4326)
    private const MARIADB_POLYGON_HEX = 'E610000001030000000100000004000000000000000000E03F000000000000D03F000000000000F83F000000000000D03F000000000000F83F000000000000F43F000000000000E03F000000000000D03F';

    #[Test]
    public function it_parses_a_mariadb_internal_point_without_axis_swap(): void
    {
        $point = WkbParser::parsePoint(hex2bin(self::MARIADB_POINT_HEX), 'mariadb');

        $this->assertInstanceOf(Point::class, $point);
        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_parses_a_mysql_internal_point_without_axis_swap(): void
    {
        // MySQL 8 internal column storage is x = longitude first, like every
        // other driver; its lat-first SRS axis order only applies to the WKT
        // conversion functions (verified against MySQL 8.4 in CI).
        $binary = $this->internalGeometry(4326, pack('V', 1) . pack('e', 39.1234) . pack('e', 27.1234));

        $point = WkbParser::parsePoint($binary, 'mysql');

        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_never_swaps_axes_for_cartesian_srid_zero(): void
    {
        $binary = $this->internalGeometry(0, pack('V', 1) . pack('e', 39.1234) . pack('e', 27.1234));

        $point = WkbParser::parsePoint($binary, 'mysql');

        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(0, $point->getSrid());
    }

    #[Test]
    public function it_parses_a_plain_wkb_point_as_srid_zero(): void
    {
        $binary = "\x01" . pack('V', 1) . pack('e', 39.1234) . pack('e', 27.1234);

        $point = WkbParser::parsePoint($binary, 'mysql');

        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(0, $point->getSrid());
    }

    #[Test]
    public function it_parses_a_big_endian_plain_wkb_point(): void
    {
        $binary = "\x00" . pack('N', 1) . pack('E', 39.1234) . pack('E', 27.1234);

        $point = WkbParser::parsePoint($binary, 'mysql');

        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
    }

    #[Test]
    public function it_parses_a_postgis_hex_ewkb_point(): void
    {
        // PostGIS canonical output: hex EWKB with the SRID flag, always lng-first.
        $hex = bin2hex("\x01" . pack('V', 0x20000001) . pack('V', 4326) . pack('e', 39.1234) . pack('e', 27.1234));

        $point = WkbParser::parsePoint($hex, 'pgsql');

        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_parses_a_mariadb_internal_polygon(): void
    {
        $polygon = WkbParser::parsePolygon(hex2bin(self::MARIADB_POLYGON_HEX), 'mariadb');

        $this->assertInstanceOf(Polygon::class, $polygon);
        $this->assertSame('POLYGON((0.5 0.25,1.5 0.25,1.5 1.25,0.5 0.25))', $polygon->toWkt());
        $this->assertSame(4326, $polygon->getSrid());
    }

    #[Test]
    public function it_parses_mysql_polygon_ring_points_without_axis_swap(): void
    {
        // Ring stored x = longitude first, as in the internal column format.
        $ring = $this->ring([[0.5, 0.25], [1.5, 0.25], [1.5, 1.25], [0.5, 0.25]]);
        $binary = $this->internalGeometry(4326, pack('V', 3) . pack('V', 1) . $ring);

        $polygon = WkbParser::parsePolygon($binary, 'mysql');

        $this->assertSame('POLYGON((0.5 0.25,1.5 0.25,1.5 1.25,0.5 0.25))', $polygon->toWkt());
    }

    #[Test]
    public function it_parses_interior_rings(): void
    {
        $exterior = $this->ring([[0, 0], [4, 0], [4, 4], [0, 0]]);
        $hole = $this->ring([[1, 1], [2, 1], [2, 2], [1, 1]]);
        $binary = $this->internalGeometry(4326, pack('V', 3) . pack('V', 2) . $exterior . $hole);

        $polygon = WkbParser::parsePolygon($binary, 'mariadb');

        $this->assertCount(2, $polygon->getRings());
        $this->assertSame('POLYGON((0 0,4 0,4 4,0 0),(1 1,2 1,2 2,1 1))', $polygon->toWkt());
    }

    #[Test]
    public function it_rejects_geometries_with_z_or_m_dimensions(): void
    {
        $ewkbz = "\x01" . pack('V', 0x80000001 | 0x20000000) . pack('V', 4326) . pack('e', 1) . pack('e', 2) . pack('e', 3);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Z or M');

        WkbParser::parsePoint($ewkbz, 'pgsql');
    }

    #[Test]
    public function it_rejects_iso_wkb_z_types(): void
    {
        $isoz = "\x01" . pack('V', 1001) . pack('e', 1) . pack('e', 2) . pack('e', 3);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Z or M');

        WkbParser::parsePoint($isoz, 'pgsql');
    }

    #[Test]
    public function it_rejects_unexpected_geometry_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected POLYGON');

        WkbParser::parsePolygon(hex2bin(self::MARIADB_POINT_HEX), 'mariadb');
    }

    #[Test]
    public function it_rejects_truncated_geometries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WkbParser::parsePoint(substr(hex2bin(self::MARIADB_POINT_HEX), 0, 20), 'mariadb');
    }

    #[Test]
    public function it_rejects_trailing_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WkbParser::parsePoint(hex2bin(self::MARIADB_POINT_HEX) . "\x00", 'mariadb');
    }

    #[Test]
    public function it_rejects_garbage_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WkbParser::parsePoint('not-a-geometry', 'mysql');
    }

    #[Test]
    public function it_detects_wkb_values(): void
    {
        $this->assertTrue(WkbParser::isWkb(hex2bin(self::MARIADB_POINT_HEX)));
        $this->assertTrue(WkbParser::isWkb(hex2bin(self::MARIADB_POLYGON_HEX)));
        $this->assertTrue(WkbParser::isWkb(self::MARIADB_POINT_HEX));

        $this->assertFalse(WkbParser::isWkb('POINT(39.1234 27.1234),4326'));
        $this->assertFalse(WkbParser::isWkb('POLYGON((0 0,1 0,1 1,0 0)),4326'));
        $this->assertFalse(WkbParser::isWkb('not-a-geometry'));
        $this->assertFalse(WkbParser::isWkb(''));
        $this->assertFalse(WkbParser::isWkb(null));
        $this->assertFalse(WkbParser::isWkb(12345));
    }

    private function internalGeometry(int $srid, string $wkbBody): string
    {
        return pack('V', $srid) . "\x01" . $wkbBody;
    }

    /**
     * @param  array<array{float, float}>  $coordinates  [x, y] pairs
     */
    private function ring(array $coordinates): string
    {
        $binary = pack('V', count($coordinates));

        foreach ($coordinates as [$x, $y]) {
            $binary .= pack('e', $x) . pack('e', $y);
        }

        return $binary;
    }
}
