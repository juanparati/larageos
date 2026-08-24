<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests;

use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Exceptions\UnsupportedDriverException;
use Juanparati\LaraGeos\Support\GeometryExpression;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class GeometryExpressionTest extends TestCase
{
    #[Test]
    public function it_resolves_the_srid_from_the_geometry(): void
    {
        $this->assertSame(3857, GeometryExpression::resolveSrid(new Point(1, 2, 3857)));
    }

    #[Test]
    public function it_falls_back_to_the_configured_default_srid(): void
    {
        // 1. Arrange
        config(['larageos.default_srid' => 4326]);

        // 2. Act & Assert
        $this->assertSame(4326, GeometryExpression::resolveSrid(new Point(1, 2, srid: null)));
    }

    #[Test]
    public function it_resolves_the_shipped_default_srid_without_an_explicit_srid(): void
    {
        $this->assertSame(4326, GeometryExpression::resolveSrid(new Point(1, 2, srid: null)));
    }

    #[Test]
    public function it_resolves_zero_when_srid_and_config_are_unset(): void
    {
        // 1. Arrange
        config(['larageos.default_srid' => null]);

        // 2. Act & Assert
        $this->assertSame(0, GeometryExpression::resolveSrid(new Point(1, 2, srid: null)));
    }

    #[Test]
    public function it_generates_geom_from_text_for_mysql(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act & Assert
        $this->assertSame(
            "ST_GeomFromText('POINT(36.1212 25.1515)', 4326, 'axis-order=long-lat')",
            GeometryExpression::geomFromText($point, 'mysql')
        );
    }

    #[Test]
    public function it_generates_geom_from_text_for_mariadb_and_pgsql(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act & Assert
        foreach (['mariadb', 'pgsql'] as $driver) {
            $this->assertSame(
                "ST_GeomFromText('POINT(36.1212 25.1515)', 4326)",
                GeometryExpression::geomFromText($point, $driver)
            );
        }
    }

    #[Test]
    public function it_omits_the_srid_when_it_resolves_to_zero(): void
    {
        // 1. Arrange
        config(['larageos.default_srid' => null]);

        $point = new Point(25.1515, 36.1212, srid: null);

        // 2. Act & Assert
        foreach (['mysql', 'mariadb', 'pgsql'] as $driver) {
            $this->assertSame(
                "ST_GeomFromText('POINT(36.1212 25.1515)')",
                GeometryExpression::geomFromText($point, $driver)
            );
        }
    }

    #[Test]
    public function it_generates_geom_from_text_for_polygons(): void
    {
        // 1. Arrange
        $polygon = new Polygon([
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 0, lng: 1, srid: null),
            new Point(lat: 1, lng: 1, srid: null),
        ], 4326);

        // 2. Act & Assert
        $this->assertSame(
            "ST_GeomFromText('POLYGON((0 0,1 0,1 1,0 0))', 4326)",
            GeometryExpression::geomFromText($polygon, 'mariadb')
        );
    }

    #[Test]
    public function it_generates_a_parameterized_point_expression_per_driver(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act & Assert
        $this->assertSame(
            ["ST_GeomFromText(?, ?, 'axis-order=long-lat')", ['POINT(36.1212 25.1515)', 4326]],
            GeometryExpression::pointExpression($point, 'mysql')
        );

        $this->assertSame(
            ['ST_GeomFromText(?, ?)', ['POINT(36.1212 25.1515)', 4326]],
            GeometryExpression::pointExpression($point, 'mariadb')
        );

        $this->assertSame(
            ['ST_SetSRID(ST_MakePoint(?, ?), ?)', [36.1212, 25.1515, 4326]],
            GeometryExpression::pointExpression($point, 'pgsql')
        );
    }

    #[Test]
    public function it_throws_for_an_unsupported_driver_in_geom_from_text(): void
    {
        // 1. Expect
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('sqlite');

        // 2. Act
        GeometryExpression::geomFromText(new Point(1, 2), 'sqlite');
    }

    #[Test]
    public function it_throws_for_an_unsupported_driver_in_point_expression(): void
    {
        // 1. Expect
        $this->expectException(UnsupportedDriverException::class);

        // 2. Act
        GeometryExpression::pointExpression(new Point(1, 2), 'sqlsrv');
    }
}
