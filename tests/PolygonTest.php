<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class PolygonTest extends TestCase
{
    private function makeTrianglePoints(): array
    {
        return [
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 0, lng: 1, srid: null),
            new Point(lat: 1, lng: 1, srid: null),
        ];
    }

    private function makeHolePoints(): array
    {
        return [
            new Point(lat: 0.2, lng: 0.5, srid: null),
            new Point(lat: 0.2, lng: 0.7, srid: null),
            new Point(lat: 0.4, lng: 0.7, srid: null),
        ];
    }

    #[Test]
    public function it_creates_a_polygon_with_points(): void
    {
        // 1. Arrange & Act
        $polygon = new Polygon($this->makeTrianglePoints(), 4326);

        // 2. Assert
        $this->assertCount(4, $polygon->getPoints());
        $this->assertSame(4326, $polygon->getSrid());
    }

    #[Test]
    public function it_creates_a_polygon_with_interior_rings(): void
    {
        // 1. Act
        $polygon = new Polygon([$this->makeTrianglePoints(), $this->makeHolePoints()], 4326);

        // 2. Assert
        $this->assertCount(2, $polygon->getRings());
        $this->assertCount(4, $polygon->getExteriorRing());
        $this->assertCount(1, $polygon->getInteriorRings());
        $this->assertCount(4, $polygon->getInteriorRings()[0]);
    }

    #[Test]
    public function it_treats_a_flat_point_list_as_the_exterior_ring(): void
    {
        // 1. Act
        $polygon = new Polygon($this->makeTrianglePoints());

        // 2. Assert
        $this->assertCount(1, $polygon->getRings());
        $this->assertSame($polygon->getExteriorRing(), $polygon->getPoints());
        $this->assertSame([], $polygon->getInteriorRings());
    }

    #[Test]
    public function it_auto_closes_every_ring(): void
    {
        // 1. Act
        $polygon = new Polygon([$this->makeTrianglePoints(), $this->makeHolePoints()]);

        // 2. Assert
        foreach ($polygon->getRings() as $ring) {
            $first = $ring[0];
            $last = end($ring);
            $this->assertSame($first->getLat(), $last->getLat());
            $this->assertSame($first->getLng(), $last->getLng());
        }
    }

    #[Test]
    public function it_does_not_double_close_an_already_closed_polygon(): void
    {
        // 1. Arrange
        $points = $this->makeTrianglePoints();
        $points[] = clone $points[0];

        // 2. Act
        $polygon = new Polygon($points);

        // 3. Assert
        $this->assertCount(4, $polygon->getPoints());
    }

    #[Test]
    public function it_throws_an_exception_with_fewer_than_3_points(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        new Polygon([
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 1, lng: 1, srid: null),
        ]);
    }

    #[Test]
    public function it_throws_an_exception_with_3_points_that_are_not_unique(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unique');

        // 2. Act
        new Polygon([
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 1, lng: 1, srid: null),
        ]);
    }

    #[Test]
    public function it_throws_an_exception_when_an_interior_ring_is_invalid(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        new Polygon([
            $this->makeTrianglePoints(),
            [new Point(lat: 0.2, lng: 0.5, srid: null)],
        ]);
    }

    #[Test]
    public function it_throws_an_exception_with_non_point_values(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        new Polygon(['not', 'points', 'here']);
    }

    #[Test]
    public function it_returns_polygon_as_wkt(): void
    {
        // 1. Arrange
        $polygon = new Polygon($this->makeTrianglePoints());

        // 2. Act
        $wkt = $polygon->toWkt();

        // 3. Assert
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0))', $wkt);
    }

    #[Test]
    public function it_returns_polygon_with_holes_as_wkt(): void
    {
        // 1. Arrange
        $polygon = new Polygon([$this->makeTrianglePoints(), $this->makeHolePoints()]);

        // 2. Act
        $wkt = $polygon->toWkt();

        // 3. Assert
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0),(0.5 0.2,0.7 0.2,0.7 0.4,0.5 0.2))', $wkt);
    }

    #[Test]
    public function it_returns_polygon_as_pairs(): void
    {
        // 1. Arrange
        $polygon = new Polygon($this->makeTrianglePoints());

        // 2. Act
        $pairs = $polygon->toPairs();

        // 3. Assert
        $this->assertSame('0 0,1 0,1 1,0 0', $pairs);
    }

    #[Test]
    public function it_creates_a_polygon_from_wkt(): void
    {
        // 1. Act
        $polygon = Polygon::fromWkt('POLYGON((0 0,1 0,1 1,0 0),(0.5 0.2,0.7 0.2,0.7 0.4,0.5 0.2))', 4326);

        // 2. Assert
        $this->assertSame(4326, $polygon->getSrid());
        $this->assertCount(2, $polygon->getRings());
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0),(0.5 0.2,0.7 0.2,0.7 0.4,0.5 0.2))', $polygon->toWkt());
    }

    #[Test]
    public function it_throws_an_exception_for_invalid_wkt(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        Polygon::fromWkt('POINT(1 2)');
    }

    #[Test]
    public function it_returns_polygon_as_array(): void
    {
        // 1. Arrange
        $polygon = new Polygon([$this->makeTrianglePoints(), $this->makeHolePoints()], 4326);

        // 2. Act
        $array = $polygon->toArray();

        // 3. Assert
        $this->assertArrayHasKey('rings', $array);
        $this->assertArrayHasKey('srid', $array);
        $this->assertCount(2, $array['rings']);
        $this->assertCount(4, $array['rings'][0]);
        $this->assertSame(4326, $array['srid']);
        $this->assertSame(0.0, $array['rings'][0][0]['lat']);
        $this->assertSame(0.0, $array['rings'][0][0]['lng']);
    }

    #[Test]
    public function it_round_trips_through_geojson(): void
    {
        // 1. Arrange
        $polygon = new Polygon([$this->makeTrianglePoints(), $this->makeHolePoints()], 4326);

        // 2. Act
        $geoJson = $polygon->toGeoJson();
        $rebuilt = Polygon::fromGeoJson($geoJson, 4326);

        // 3. Assert
        $this->assertSame('Polygon', $geoJson['type']);
        $this->assertSame([0.0, 0.0], $geoJson['coordinates'][0][0]);
        $this->assertSame($polygon->toWkt(), $rebuilt->toWkt());
        $this->assertSame($polygon->getSrid(), $rebuilt->getSrid());
    }

    #[Test]
    public function it_creates_a_polygon_from_a_geojson_string(): void
    {
        // 1. Act
        $polygon = Polygon::fromGeoJson('{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}');

        // 2. Assert
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0))', $polygon->toWkt());
        $this->assertSame(4326, $polygon->getSrid());
    }

    #[Test]
    public function it_throws_an_exception_for_geojson_of_a_different_geometry_type(): void
    {
        // 1. Expect
        $this->expectException(InvalidArgumentException::class);

        // 2. Act
        Polygon::fromGeoJson(['type' => 'Point', 'coordinates' => [1, 2]]);
    }

    #[Test]
    public function it_serializes_to_geojson_via_json_encode(): void
    {
        // 1. Arrange
        $polygon = new Polygon($this->makeTrianglePoints());

        // 2. Act
        $json = json_encode($polygon);

        // 3. Assert
        $this->assertSame('{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}', $json);
    }
}
