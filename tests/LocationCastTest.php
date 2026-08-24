<?php

namespace Juanparati\LaraGeos\Tests;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Casts\LocationCast;
use Juanparati\LaraGeos\Tests\TestModels\Address;
use Juanparati\LaraGeos\Types\Point;

class LocationCastTest extends TestCase
{
    #[Test]
    public function it_throws_an_exception_if_casted_attribute_set_to_a_non_point_value(): void
    {
        // 1. Arrange
        $address = new Address();

        // 2. Expect
        $this->expectException(InvalidArgumentException::class);

        // 3. Act
        $address->location = 'dummy';
    }

    #[Test]
    public function it_can_set_the_casted_attribute_to_a_point(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234);

        $cast = new LocationCast();

        // 2. Act
        $response = $cast->set($address, 'location', $point, $address->getAttributes());

        // 3. Assert
        $this->assertInstanceOf(Expression::class, $response);
        $this->assertSame(
            $this->geomFromTextSql($point),
            (string) $response->getValue($address->getConnection()->getQueryGrammar())
        );
    }

    #[Test]
    public function it_omits_the_srid_when_it_is_unspecified(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234, srid: null);

        $cast = new LocationCast();

        // 2. Act
        $response = $cast->set($address, 'location', $point, $address->getAttributes());

        // 3. Assert
        $this->assertSame(
            "ST_GeomFromText('{$point->toWkt()}')",
            (string) $response->getValue($address->getConnection()->getQueryGrammar())
        );
    }

    #[Test]
    public function it_can_get_a_casted_attribute(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234);

        // 2. Act
        $address->location = $point;
        $address->save();

        // 3. Assert
        $this->assertInstanceOf(Point::class, $address->location);
        $this->assertEquals($point->getLat(), $address->location->getLat());
        $this->assertEquals($point->getLng(), $address->location->getLng());
        $this->assertEquals($point->getSrid(), $address->location->getSrid());
    }

    #[Test]
    public function it_round_trips_a_point_through_the_database(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234);

        // 2. Act
        $address->location = $point;
        $address->save();
        $address->refresh();

        // 3. Assert
        $this->assertInstanceOf(Point::class, $address->location);
        $this->assertEqualsWithDelta($point->getLat(), $address->location->getLat(), 1e-9);
        $this->assertEqualsWithDelta($point->getLng(), $address->location->getLng(), 1e-9);
        $this->assertSame($point->getSrid(), $address->location->getSrid());
    }

    #[Test]
    public function it_can_get_a_casted_attribute_from_stored_wkb(): void
    {
        // 1. Arrange
        $address = new Address();
        $cast = new LocationCast();

        $stored = $this->storedPointWkb(lat: 27.1234, lng: 39.1234, srid: 4326);

        // 2. Act
        $point = $cast->get($address, 'location', $stored, []);

        // 3. Assert
        $this->assertInstanceOf(Point::class, $point);
        $this->assertSame(27.1234, $point->getLat());
        $this->assertSame(39.1234, $point->getLng());
        $this->assertSame(4326, $point->getSrid());
    }

    #[Test]
    public function it_can_get_a_casted_attribute_using_expression(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234);

        // 2. Act
        $cast   = new LocationCast();
        $result = $cast->get($address, 'location', new Expression($this->geomFromTextSql($point)), $address->getAttributes());

        // 3. Assert
        $this->assertInstanceOf(Point::class, $result);
        $this->assertEquals($point->getLat(), $result->getLat());
        $this->assertEquals($point->getLng(), $result->getLng());
        $this->assertEquals($point->getSrid(), $result->getSrid());
    }

    #[Test]
    public function it_survives_replication_of_an_unsaved_spatial_attribute(): void
    {
        // replicate() copies raw attributes (the Expression from set()) and
        // clears the class cast cache, forcing get() through the fallback.

        // 1. Arrange
        $address = new Address();
        $address->location = new Point(27.1234, 39.1234);
        $address->save();

        // 2. Act
        $replica = $address->replicate();

        // 3. Assert
        $this->assertInstanceOf(Point::class, $replica->location);
        $this->assertSame(27.1234, $replica->location->getLat());
        $this->assertSame(39.1234, $replica->location->getLng());
        $this->assertSame(4326, $replica->location->getSrid());
    }

    #[Test]
    public function it_returns_null_if_the_value_of_the_casted_column_is_null(): void
    {
        // 1. Arrange
        $address = new Address();

        // 2. Act
        $address->save();

        // 3. Assert
        $this->assertNull($address->location);
    }

    #[Test]
    public function it_can_serialize_a_casted_attribute(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point(27.1234, 39.1234);

        // 2. Act
        $address->location = $point;
        $address->save();

        // 3. Assert
        $array = $address->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('location', $array);
        $this->assertArrayHasKey('lat', $array['location']);
        $this->assertArrayHasKey('lng', $array['location']);
        $this->assertArrayHasKey('srid', $array['location']);

        $this->assertJson($address->toJson());
    }
}
