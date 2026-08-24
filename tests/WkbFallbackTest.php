<?php

namespace Juanparati\LaraGeos\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Juanparati\LaraGeos\Tests\TestModels\Region;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

class WkbFallbackTest extends TestCase
{
    #[Test]
    public function it_hydrates_raw_rows_from_stored_wkb(): void
    {
        $location = new Point(27.1234, 39.1234);
        $area = new Polygon([
            new Point(0.25, 0.5),
            new Point(0.25, 1.5),
            new Point(1.25, 1.5),
            new Point(0.25, 0.5),
        ]);

        $region = new Region();
        $region->location = $location;
        $region->area = $area;
        $region->save();

        $raw = DB::table('regions')->where('id', $region->id)->first();
        $this->assertRoundTrip((new Region())->newFromBuilder((array) $raw), $location, $area);

        $hydrated = Region::hydrate(DB::select('select * from regions where id = ?', [$region->id]))->first();
        $this->assertRoundTrip($hydrated, $location, $area);
    }

    private function assertRoundTrip(Region $region, Point $location, Polygon $area): void
    {
        $this->assertInstanceOf(Point::class, $region->location);
        $this->assertEqualsWithDelta($location->getLat(), $region->location->getLat(), 1e-9);
        $this->assertEqualsWithDelta($location->getLng(), $region->location->getLng(), 1e-9);
        $this->assertSame($location->getSrid(), $region->location->getSrid());

        $this->assertInstanceOf(Polygon::class, $region->area);
        $this->assertSame($area->toWkt(), $region->area->toWkt());
        $this->assertSame($area->getSrid(), $region->area->getSrid());
    }
}
