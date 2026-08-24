<?php

namespace Juanparati\LaraGeos\Tests;

use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Tests\TestModels\Address;
use Juanparati\LaraGeos\Tests\TestModels\Place;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class HasGeoSpatialPredicatesTest extends TestCase
{
    /**
     * A square spanning lat/lng 0..4 (closed automatically).
     */
    private function square(float $offsetLat = 0, float $offsetLng = 0, float $size = 4): Polygon
    {
        return new Polygon([
            new Point(lat: $offsetLat, lng: $offsetLng),
            new Point(lat: $offsetLat, lng: $offsetLng + $size),
            new Point(lat: $offsetLat + $size, lng: $offsetLng + $size),
            new Point(lat: $offsetLat + $size, lng: $offsetLng),
        ]);
    }

    #[Test]
    public function it_generates_sql_query_for_whereContains_scope(): void
    {
        // 1. Arrange
        $point = new Point(lat: 2, lng: 2);

        // 2. Act
        $query = Place::query()->whereContains('area', $point);

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('places')} where {$this->containsFnSql()}({$this->wrap('area')}, {$this->pointExprSql()})",
            actual: $query->toSql()
        );

        $this->assertEquals($this->pointExprBindings($point), $query->getBindings());
    }

    #[Test]
    public function it_generates_sql_query_for_whereWithin_scope(): void
    {
        // 1. Arrange
        $polygon = $this->square();

        // 2. Act
        $query = Address::query()->whereWithin('location', $polygon);

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('addresses')} where {$this->withinFnSql()}({$this->wrap('location')}, {$this->polygonExprSql()})",
            actual: $query->toSql()
        );

        $this->assertEquals($this->polygonExprBindings($polygon), $query->getBindings());
    }

    #[Test]
    public function it_generates_sql_query_for_whereIntersects_scope(): void
    {
        // 1. Arrange
        $polygon = $this->square();

        // 2. Act
        $query = Place::query()->whereIntersects('area', $polygon);

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('places')} where ST_Intersects({$this->wrap('area')}, {$this->polygonExprSql()})",
            actual: $query->toSql()
        );

        $this->assertEquals($this->polygonExprBindings($polygon), $query->getBindings());
    }

    #[Test]
    public function it_finds_rows_whose_polygon_contains_a_point(): void
    {
        // 1. Arrange
        $place = Place::create(['area' => $this->square()]);

        // 2. Act
        $inside = Place::query()->whereContains('area', new Point(lat: 2, lng: 2))->get();
        $outside = Place::query()->whereContains('area', new Point(lat: 10, lng: 10))->get();

        // 3. Assert
        $this->assertCount(1, $inside);
        $this->assertTrue($inside->first()->is($place));
        $this->assertCount(0, $outside);
    }

    #[Test]
    public function it_finds_rows_whose_polygon_contains_a_polygon(): void
    {
        // 1. Arrange
        $place = Place::create(['area' => $this->square()]);

        // 2. Act
        $inside = Place::query()->whereContains('area', $this->square(1, 1, 2))->get();
        $overlapping = Place::query()->whereContains('area', $this->square(2, 2, 4))->get();

        // 3. Assert
        $this->assertCount(1, $inside);
        $this->assertTrue($inside->first()->is($place));
        $this->assertCount(0, $overlapping);
    }

    #[Test]
    public function it_filters_points_within_a_polygon(): void
    {
        // 1. Arrange
        $inside = Address::create(['location' => new Point(lat: 2, lng: 2)]);
        Address::create(['location' => new Point(lat: 10, lng: 10)]);

        // 2. Act
        $found = Address::query()->whereWithin('location', $this->square())->get();

        // 3. Assert
        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($inside));
    }

    #[Test]
    public function it_filters_polygons_intersecting_a_polygon(): void
    {
        // 1. Arrange
        $place = Place::create(['area' => $this->square()]);

        // 2. Act
        $overlapping = Place::query()->whereIntersects('area', $this->square(2, 2))->get();
        $disjoint = Place::query()->whereIntersects('area', $this->square(10, 10))->get();

        // 3. Assert
        $this->assertCount(1, $overlapping);
        $this->assertTrue($overlapping->first()->is($place));
        $this->assertCount(0, $disjoint);
    }

    #[Test]
    public function it_filters_polygons_intersecting_a_point(): void
    {
        // 1. Arrange
        $place = Place::create(['area' => $this->square()]);

        // 2. Act
        $inside = Place::query()->whereIntersects('area', new Point(lat: 2, lng: 2))->get();
        $outside = Place::query()->whereIntersects('area', new Point(lat: 10, lng: 10))->get();

        // 3. Assert
        $this->assertCount(1, $inside);
        $this->assertTrue($inside->first()->is($place));
        $this->assertCount(0, $outside);
    }
}
