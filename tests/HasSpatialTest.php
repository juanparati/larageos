<?php

namespace Juanparati\LaraGeos\Tests;

use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Tests\TestModels\Address;
use Juanparati\LaraGeos\Tests\TestModels\Region;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class HasSpatialTest extends TestCase
{
    #[Test]
    public function it_returns_both_location_and_area_casted_attributes(): void
    {
        // 1. Arrange
        $region = new Region();

        // 2. Act
        $locationAttributes = $region->getLocationCastedAttributes();
        $areaAttributes = $region->getRegionCastedAttributes();

        // 3. Assert
        $this->assertEquals(collect(['location']), $locationAttributes);
        $this->assertEquals(collect(['area']), $areaAttributes);
    }

    #[Test]
    public function it_generates_a_plain_select_with_both_spatial_columns(): void
    {
        // 1. Arrange
        $region = new Region();

        // 2. Act & Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('regions')}",
            actual: $region->query()->toSql()
        );
    }

    #[Test]
    public function it_generates_sql_query_for_selectDistanceTo_with_both_spatial_columns(): void
    {
        // 1. Arrange
        $region = new Region();

        // 2. Act
        $query = $region->selectDistanceTo('location', new Point());

        // 3. Assert
        $this->assertEquals(
            expected: "select {$this->wrap('regions')}.*, ST_Distance({$this->wrap('location')}, {$this->pointExprSql()}) as distance from {$this->wrap('regions')}",
            actual: $query->toSql()
        );
    }

    #[Test]
    public function it_generates_sql_query_for_withinDistanceTo_with_both_spatial_columns(): void
    {
        // 1. Arrange
        $region = new Region();

        // 2. Act
        $query = $region->withinDistanceTo('location', new Point(), 10000);

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('regions')} where ST_Distance({$this->wrap('location')}, {$this->pointExprSql()}) <= ?",
            actual: $query->toSql()
        );
    }

    #[Test]
    public function it_generates_sql_query_for_orderByDistanceTo_with_both_spatial_columns(): void
    {
        // 1. Arrange
        $region = new Region();

        // 2. Act
        $queryForAsc = $region->orderByDistanceTo('location', new Point());
        $queryForDesc = $region->orderByDistanceTo('location', new Point(), 'desc');

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('regions')} order by ST_Distance({$this->wrap('location')}, {$this->pointExprSql()}) asc",
            actual: $queryForAsc->toSql()
        );

        $this->assertEquals(
            expected: "select * from {$this->wrap('regions')} order by ST_Distance({$this->wrap('location')}, {$this->pointExprSql()}) desc",
            actual: $queryForDesc->toSql()
        );
    }

    #[Test]
    public function it_selects_the_distance_between_two_points(): void
    {
        // 1. Arrange
        $origin = new Point(lat: 0, lng: 0);
        $stored = new Point(lat: 0, lng: 0.5);

        Address::create(['location' => $stored]);

        // 2. Act
        $distance = Address::query()->selectDistanceTo('location', $origin)->first()->distance;

        // 3. Assert
        [$min, $max] = $this->expectedDistanceBetween($origin, $stored);
        $this->assertGreaterThanOrEqual($min, (float) $distance);
        $this->assertLessThanOrEqual($max, (float) $distance);
    }

    #[Test]
    public function it_filters_rows_within_a_distance(): void
    {
        // 1. Arrange
        $origin = new Point(lat: 0, lng: 0);
        $near = Address::create(['location' => new Point(lat: 0, lng: 0.5)]);
        Address::create(['location' => new Point(lat: 0, lng: 1.0)]);

        // Threshold between the two rows, in the driver's ST_Distance unit.
        $threshold = $this->driver() === 'mariadb' ? 0.75 : 83_490.0;

        // 2. Act
        $found = Address::query()->withinDistanceTo('location', $origin, $threshold)->get();

        // 3. Assert
        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($near));
    }

    #[Test]
    public function it_orders_rows_by_distance(): void
    {
        // 1. Arrange
        $origin = new Point(lat: 0, lng: 0);
        $far = Address::create(['location' => new Point(lat: 0, lng: 1.0)]);
        $near = Address::create(['location' => new Point(lat: 0, lng: 0.5)]);

        // 2. Act
        $ascending = Address::query()->orderByDistanceTo('location', $origin)->get();
        $descending = Address::query()->orderByDistanceTo('location', $origin, 'desc')->get();

        // 3. Assert
        $this->assertTrue($ascending->first()->is($near));
        $this->assertTrue($descending->first()->is($far));
    }

    #[Test]
    public function it_keeps_spatial_attributes_casted_after_refresh(): void
    {
        // 1. Arrange
        $location = new Point(27.1234, 39.1234);
        $area = new Polygon([
            new Point(0, 0),
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 0),
        ]);

        $region = new Region();
        $region->location = $location;
        $region->area = $area;
        $region->save();

        // 2. Act
        $region->refresh();

        // 3. Assert
        $this->assertInstanceOf(Point::class, $region->location);
        $this->assertEquals($location->getLat(), $region->location->getLat());
        $this->assertEquals($location->getLng(), $region->location->getLng());
        $this->assertEquals($location->getSrid(), $region->location->getSrid());

        $this->assertInstanceOf(Polygon::class, $region->area);
        $this->assertEquals($area->toWkt(), $region->area->toWkt());
        $this->assertEquals($area->getSrid(), $region->area->getSrid());
    }

    #[Test]
    public function it_keeps_spatial_attributes_casted_on_fresh(): void
    {
        // 1. Arrange
        $location = new Point(27.1234, 39.1234);
        $area = new Polygon([
            new Point(0, 0),
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 0),
        ]);

        $region = new Region();
        $region->location = $location;
        $region->area = $area;
        $region->save();

        // 2. Act
        $fresh = $region->fresh();

        // 3. Assert
        $this->assertInstanceOf(Point::class, $fresh->location);
        $this->assertEquals($location->getLat(), $fresh->location->getLat());
        $this->assertEquals($location->getLng(), $fresh->location->getLng());
        $this->assertEquals($location->getSrid(), $fresh->location->getSrid());

        $this->assertInstanceOf(Polygon::class, $fresh->area);
        $this->assertEquals($area->toWkt(), $fresh->area->toWkt());
        $this->assertEquals($area->getSrid(), $fresh->area->getSrid());
    }
}
