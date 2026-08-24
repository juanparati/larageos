<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Types\Point;

class PointTest extends TestCase
{
    #[Test]
    public function it_sets_lat_lng_and_srid_in_constructor(): void
    {
        // 1. Arrange
        $lat = 25.1515;
        $lng = 36.1212;
        $srid = 4326;

        // 2. Act
        $point = new Point(lat: $lat, lng: $lng, srid: $srid);

        // 3. Assert
        $this->assertSame(expected: $lat, actual: $point->getLat());
        $this->assertSame(expected: $lng, actual: $point->getLng());
        $this->assertSame(expected: $srid, actual: $point->getSrid());
    }

    #[Test]
    public function it_returns_default_lat_lng_and_srid_if_they_are_not_given_in_the_constructor(): void
    {
        // 1. Act
        $point = new Point();

        // 2. Assert
        $this->assertSame(expected: 0.0, actual: $point->getLat());
        $this->assertSame(expected: 0.0, actual: $point->getLng());
        $this->assertSame(expected: 4326, actual: $point->getSrid());
    }

    #[Test]
    public function it_stores_a_null_srid_as_zero(): void
    {
        // 1. Act
        $point = new Point(lat: 1, lng: 2, srid: null);

        // 2. Assert
        $this->assertSame(0, $point->getSrid());
    }

    #[Test]
    public function it_accepts_boundary_coordinates(): void
    {
        // 1. Act
        $southWest = new Point(lat: -90, lng: -180);
        $northEast = new Point(lat: 90, lng: 180);

        // 2. Assert
        $this->assertSame(-90.0, $southWest->getLat());
        $this->assertSame(-180.0, $southWest->getLng());
        $this->assertSame(90.0, $northEast->getLat());
        $this->assertSame(180.0, $northEast->getLng());
    }

    #[Test]
    public function it_throws_an_exception_for_an_out_of_range_latitude(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('latitude');

        // 2. Act
        new Point(lat: 90.0001, lng: 0);
    }

    #[Test]
    public function it_throws_an_exception_for_an_out_of_range_longitude(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('longitude');

        // 2. Act
        new Point(lat: 0, lng: -180.0001);
    }

    #[Test]
    public function it_returns_point_as_wkt(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act
        $wkt = $point->toWkt();

        // 3. Assert
        $this->assertSame("POINT({$point->getLng()} {$point->getLat()})", $wkt);
    }

    #[Test]
    public function it_returns_point_as_pair(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act
        $pair = $point->toPair();

        // 3. Assert
        $this->assertSame("{$point->getLng()} {$point->getLat()}", $pair);
    }

    #[Test]
    public function it_creates_a_point_from_wkt(): void
    {
        // 1. Act
        $point = Point::fromWkt('POINT(36.1212 25.1515)', 4326);

        // 2. Assert
        $this->assertSame(25.1515, $point->getLat());
        $this->assertSame(36.1212, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_defaults_to_srid_zero_when_created_from_wkt_without_srid(): void
    {
        // 1. Act
        $point = Point::fromWkt('POINT(1 2)');

        // 2. Assert
        $this->assertSame(0, $point->getSrid());
    }

    #[Test]
    public function it_throws_an_exception_for_invalid_wkt(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        Point::fromWkt('POLYGON((0 0,1 0,1 1,0 0))');
    }

    #[Test]
    public function it_returns_points_as_array(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act
        $array = $point->toArray();

        $expected = [
            'lat'   => $point->getLat(),
            'lng'   => $point->getLng(),
            'srid'  => $point->getSrid(),
        ];

        // 3. Assert
        $this->assertSame($expected, $array);
    }

    #[Test]
    public function it_returns_point_as_geojson(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act
        $geoJson = $point->toGeoJson();

        // 3. Assert
        $this->assertSame([
            'type'        => 'Point',
            'coordinates' => [36.1212, 25.1515],
        ], $geoJson);
    }

    #[Test]
    public function it_creates_a_point_from_a_geojson_array(): void
    {
        // 1. Act
        $point = Point::fromGeoJson([
            'type'        => 'Point',
            'coordinates' => [36.1212, 25.1515],
        ]);

        // 2. Assert
        $this->assertSame(25.1515, $point->getLat());
        $this->assertSame(36.1212, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_creates_a_point_from_a_geojson_string(): void
    {
        // 1. Act
        $point = Point::fromGeoJson('{"type":"Point","coordinates":[36.1212,25.1515]}', srid: 3857);

        // 2. Assert
        $this->assertSame(25.1515, $point->getLat());
        $this->assertSame(36.1212, $point->getLng());
        $this->assertSame(3857, $point->getSrid());
    }

    #[Test]
    public function it_throws_an_exception_for_geojson_of_a_different_geometry_type(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        Point::fromGeoJson(['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]]);
    }

    #[Test]
    public function it_throws_an_exception_for_malformed_geojson_strings(): void
    {
        // 1. Expect
        $this->expectException(JsonException::class);

        // 2. Act
        Point::fromGeoJson('{not json');
    }

    #[Test]
    public function it_serializes_to_geojson_via_json_encode(): void
    {
        // 1. Arrange
        $point = new Point(25.1515, 36.1212, 4326);

        // 2. Act
        $json = json_encode($point);

        // 3. Assert
        $this->assertSame('{"type":"Point","coordinates":[36.1212,25.1515]}', $json);
    }
}
