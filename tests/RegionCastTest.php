<?php

namespace Juanparati\LaraGeos\Tests;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Casts\RegionCast;
use Juanparati\LaraGeos\Tests\TestModels\Place;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class RegionCastTest extends TestCase
{
    private function makeTrianglePolygon(): Polygon
    {
        return new Polygon([
            new Point(lat: 0, lng: 0, srid: null),
            new Point(lat: 0, lng: 1, srid: null),
            new Point(lat: 1, lng: 1, srid: null),
        ], 4326);
    }

    private function makePolygonWithHole(): Polygon
    {
        return new Polygon([
            [
                new Point(lat: 0, lng: 0, srid: null),
                new Point(lat: 0, lng: 4, srid: null),
                new Point(lat: 4, lng: 4, srid: null),
                new Point(lat: 4, lng: 0, srid: null),
            ],
            [
                new Point(lat: 1, lng: 1, srid: null),
                new Point(lat: 2, lng: 1, srid: null),
                new Point(lat: 2, lng: 2, srid: null),
                new Point(lat: 1, lng: 2, srid: null),
            ],
        ], 4326);
    }

    #[Test]
    public function it_can_get_a_casted_attribute_from_stored_wkb(): void
    {
        // 1. Arrange
        $place = new Place();
        $cast = new RegionCast();

        $stored = $this->storedPolygonWkb([
            [0.25, 0.5],
            [0.25, 1.5],
            [1.25, 1.5],
            [0.25, 0.5],
        ], srid: 4326);

        // 2. Act
        $polygon = $cast->get($place, 'area', $stored, []);

        // 3. Assert
        $this->assertInstanceOf(Polygon::class, $polygon);
        $this->assertSame('POLYGON((0.5 0.25,1.5 0.25,1.5 1.25,0.5 0.25))', $polygon->toWkt());
        $this->assertSame(4326, $polygon->getSrid());
    }

    #[Test]
    public function it_throws_an_exception_if_casted_attribute_set_to_a_non_polygon_value(): void
    {
        // 1. Arrange
        $address = new Place();

        // 2. Expect
        $this->expectException(InvalidArgumentException::class);

        // 3. Act
        $address->area = 'dummy';
    }

    #[Test]
    public function it_can_set_the_casted_attribute_to_a_polygon(): void
    {
        // 1. Arrange
        $address = new Place();
        $polygon = $this->makeTrianglePolygon();

        $cast = new RegionCast();

        // 2. Act
        $response = $cast->set($address, 'area', $polygon, $address->getAttributes());

        // 3. Assert
        $this->assertInstanceOf(Expression::class, $response);
        $this->assertSame(
            $this->geomFromTextSql($polygon),
            (string) $response->getValue($address->getConnection()->getQueryGrammar())
        );
    }

    #[Test]
    public function it_can_get_a_casted_attribute(): void
    {
        // 1. Arrange
        $address = new Place();
        $polygon = $this->makeTrianglePolygon();

        // 2. Act
        $address->area = $polygon;
        $address->save();

        // 3. Assert
        $this->assertInstanceOf(Polygon::class, $address->area);
        $this->assertCount(count($polygon->getPoints()), $address->area->getPoints());
        $this->assertEquals($polygon->getSrid(), $address->area->getSrid());
    }

    #[Test]
    public function it_round_trips_a_polygon_with_holes_through_the_database(): void
    {
        // 1. Arrange
        $place = new Place();
        $polygon = $this->makePolygonWithHole();

        // 2. Act
        $place->area = $polygon;
        $place->save();
        $place->refresh();

        // 3. Assert
        $this->assertInstanceOf(Polygon::class, $place->area);
        $this->assertCount(2, $place->area->getRings());
        $this->assertSame($polygon->toWkt(), $place->area->toWkt());
        $this->assertSame($polygon->getSrid(), $place->area->getSrid());
    }

    #[Test]
    public function it_can_get_a_casted_attribute_using_expression(): void
    {
        // 1. Arrange
        $address = new Place();
        $polygon = $this->makeTrianglePolygon();

        // 2. Act
        $cast   = new RegionCast();
        $result = $cast->get($address, 'area', new Expression($this->geomFromTextSql($polygon)), $address->getAttributes());

        // 3. Assert
        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertCount(count($polygon->getPoints()), $result->getPoints());
        $this->assertEquals($polygon->getSrid(), $result->getSrid());
    }

    #[Test]
    public function it_survives_replication_of_a_spatial_attribute(): void
    {
        // replicate() copies raw attributes (the Expression from set()) and
        // clears the class cast cache, forcing get() through the fallback.

        // 1. Arrange
        $place = new Place();
        $place->area = $this->makeTrianglePolygon();
        $place->save();

        // 2. Act
        $replica = $place->replicate();

        // 3. Assert
        $this->assertInstanceOf(Polygon::class, $replica->area);
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0))', $replica->area->toWkt());
        $this->assertSame(4326, $replica->area->getSrid());
    }

    #[Test]
    public function it_returns_null_if_the_value_of_the_casted_column_is_null(): void
    {
        // 1. Arrange
        $address = new Place();

        // 2. Act
        $address->save();

        // 3. Assert
        $this->assertNull($address->area);
    }

    #[Test]
    public function it_can_serialize_a_casted_attribute(): void
    {
        // 1. Arrange
        $address = new Place();
        $polygon = $this->makeTrianglePolygon();

        // 2. Act
        $address->area = $polygon;
        $address->save();

        // 3. Assert
        $array = $address->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('area', $array);
        $this->assertArrayHasKey('rings', $array['area']);
        $this->assertArrayHasKey('srid', $array['area']);

        $this->assertJson($address->toJson());
    }
}
