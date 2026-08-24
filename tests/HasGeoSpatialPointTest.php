<?php

namespace Juanparati\LaraGeos\Tests;

use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Tests\TestModels\Address;
use Juanparati\LaraGeos\Types\Point;

class HasGeoSpatialPointTest extends TestCase
{
    #[Test]
    public function it_generates_sql_query_for_selectDistanceTo_scope(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point();
        $castedAttr = $address->getLocationCastedAttributes()->first();

        // 2. Act
        $query = $address->selectDistanceTo($castedAttr, $point);

        // 3. Assert
        $this->assertEquals(
            expected: "select {$this->wrap('addresses')}.*, {$this->distanceFnSql()}({$this->wrap($castedAttr)}, {$this->pointExprSql()}) as distance from {$this->wrap('addresses')}",
            actual: $query->toSql()
        );

        $this->assertEquals($this->pointExprBindings($point), $query->getBindings());
    }

    #[Test]
    public function it_generates_sql_query_for_withinDistanceTo_scope(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point();
        $castedAttr = $address->getLocationCastedAttributes()->first();

        // 2. Act
        $query = $address->withinDistanceTo($castedAttr, $point, 10000);

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('addresses')} where {$this->distanceFnSql()}({$this->wrap($castedAttr)}, {$this->pointExprSql()}) <= ?",
            actual: $query->toSql()
        );

        $this->assertEquals([...$this->pointExprBindings($point), 10000.0], $query->getBindings());
    }

    #[Test]
    public function it_generates_sql_query_for_orderByDistanceTo_scope(): void
    {
        // 1. Arrange
        $address = new Address();
        $point = new Point();
        $castedAttr = $address->getLocationCastedAttributes()->first();

        // 2. Act
        $queryForAsc = $address->orderByDistanceTo($castedAttr, $point);
        $queryForDesc = $address->orderByDistanceTo($castedAttr, $point, 'desc');

        // 3. Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('addresses')} order by {$this->distanceFnSql()}({$this->wrap($castedAttr)}, {$this->pointExprSql()}) asc",
            actual: $queryForAsc->toSql()
        );

        $this->assertEquals(
            expected: "select * from {$this->wrap('addresses')} order by {$this->distanceFnSql()}({$this->wrap($castedAttr)}, {$this->pointExprSql()}) desc",
            actual: $queryForDesc->toSql()
        );

        $this->assertEquals($this->pointExprBindings($point), $queryForAsc->getBindings());
    }

    #[Test]
    public function it_generates_a_plain_select_for_spatial_models(): void
    {
        // 1. Arrange
        $address = new Address();

        // 2. Act & Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('addresses')}",
            actual: $address->query()->toSql()
        );
    }

    #[Test]
    public function it_returns_location_casted_attributes(): void
    {
        // 1. Arrange
        $address = new Address();

        // 2. Act
        $locationCastedAttributres = $address->getLocationCastedAttributes();

        // 3. Assert
        $this->assertEquals(collect(['location']), $locationCastedAttributres);
    }
}
