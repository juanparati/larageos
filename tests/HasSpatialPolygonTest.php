<?php

namespace Juanparati\LaraGeos\Tests;

use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Tests\TestModels\Place;

class HasSpatialPolygonTest extends TestCase
{
    #[Test]
    public function it_generates_a_plain_select_for_polygon_models(): void
    {
        // 1. Arrange
        $place = new Place();

        // 2. Act & Assert
        $this->assertEquals(
            expected: "select * from {$this->wrap('places')}",
            actual: $place->query()->toSql()
        );
    }

    #[Test]
    public function it_returns_area_casted_attributes(): void
    {
        // 1. Arrange
        $place = new Place();

        // 2. Act
        $areaCastedAttributes = $place->getRegionCastedAttributes();

        // 3. Assert
        $this->assertEquals(collect(['area']), $areaCastedAttributes);
    }
}
