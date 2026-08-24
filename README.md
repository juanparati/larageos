# LaraGeos

![Tests](https://github.com/juanparati/larageos/actions/workflows/tests.yml/badge.svg)

A Laravel GeoSpatial library for your ORM. Store points and polygons in native
spatial columns, query them with distance scopes, and exchange them as GeoJSON.

Based on [Laravel Spatial](https://github.com/tarfin-labs/laravel-spatial) by Tarfin Labs.

## Requirements

- PHP 8.2+
- Laravel 12+
- One of:
  - **MySQL 8.0+** (the library relies on the `axis-order` WKT option introduced in 8.0)
  - **MariaDB 10.6+** — you **must** use Laravel's `mariadb` driver, not `mysql`.
    Writes on the `mysql` driver generate MySQL-specific SQL (the three-argument
    `ST_GeomFromText` with `axis-order`) that MariaDB rejects.
  - **PostgreSQL with PostGIS**

## Installation

```bash
composer require juanparati/larageos
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=larageos
```

```php
// config/larageos.php
return [
    // SRID applied when a geometry is stored without an explicit SRID.
    'default_srid' => 4326,
];
```

## Migrations

Use Laravel's native `geography()` (or `geometry()`) column types:

```php
Schema::create('addresses', function (Blueprint $table) {
    $table->id();
    $table->geography('location', subtype: 'point');          // SRID 4326 by default
    $table->geography('area', subtype: 'polygon')->nullable();
    $table->timestamps();
});
```

What that creates per database:

| Driver  | Column type            | Notes                                  |
|---------|------------------------|----------------------------------------|
| MySQL   | `point SRID 4326`      | SRID-constrained geometry              |
| MariaDB | `point ref_system_id=4326` | Cartesian semantics                 |
| PostGIS | `geography(point,4326)`| True geography type                    |

## Casts

Cast columns to rich `Point` / `Polygon` value objects:

```php
use Juanparati\LaraGeos\Casts\LocationCast;
use Juanparati\LaraGeos\Casts\RegionCast;
use Juanparati\LaraGeos\Traits\HasSpatial;

class Address extends Model
{
    use HasSpatial;

    protected $casts = [
        'location' => LocationCast::class,  // Point
        'area'     => RegionCast::class,    // Polygon
    ];
}
```

### Points

```php
use Juanparati\LaraGeos\Types\Point;

$address = new Address();
$address->location = new Point(lat: 27.1234, lng: 39.1234); // SRID 4326 by default
$address->save();

$address->refresh();
$address->location->getLat();
$address->location->getLng();
$address->location->getSrid();
$address->location->toWkt();     // POINT(39.1234 27.1234)
```

Latitude must be within [-90, 90] and longitude within [-180, 180]; out-of-range
values throw an `InvalidArgumentException`.

### Polygons

Polygons support interior rings (holes) and round-trip them faithfully:

```php
use Juanparati\LaraGeos\Types\Polygon;

// A flat list of points is the exterior ring (auto-closed):
$area = new Polygon([
    new Point(lat: 0, lng: 0),
    new Point(lat: 0, lng: 4),
    new Point(lat: 4, lng: 4),
    new Point(lat: 4, lng: 0),
]);

// Or pass rings: [exterior, ...holes]
$area = new Polygon([
    [$p1, $p2, $p3, $p4],   // exterior ring
    [$h1, $h2, $h3],        // hole
]);

$area->getExteriorRing();   // Point[]
$area->getInteriorRings();  // Point[][]
$area->getRings();          // Point[][] (exterior first)
$area->toWkt();             // POLYGON((...),(...))
```

Every ring needs at least 3 unique points and is closed automatically.

## Distance scopes

Models using `HasSpatial` get three query scopes:

```php
$center = new Point(lat: 27.1234, lng: 39.1234);

// Add a `distance` column to the select:
Address::query()->selectDistanceTo('location', $center)->get();

// Only rows within the given distance (unit: see the table below!):
Address::query()->withinDistanceTo('location', $center, 10_000)->get();

// Order by proximity:
Address::query()->orderByDistanceTo('location', $center)->get();          // nearest first
Address::query()->orderByDistanceTo('location', $center, 'desc')->get();  // farthest first
```

### Distance units

Distances — both the `distance` value returned by `selectDistanceTo` and the
threshold passed to `withinDistanceTo` — are in **meters** on every driver:

| Driver  | Function             | Model                                        |
|---------|----------------------|----------------------------------------------|
| MySQL 8 | `ST_Distance` on geographic SRIDs | geodesic (ellipsoid)            |
| MariaDB | `ST_Distance_Sphere` | spherical (within ~0.5% of ellipsoid results) |
| PostGIS | `ST_Distance` on `geography` columns | geodesic (ellipsoid)           |

Caveats:

- **MariaDB**: `ST_Distance_Sphere` only accepts POINT geometries, so the
  distance scopes work on point columns only there (polygon columns throw a
  database error).
- **PostGIS `geometry` columns**: `ST_Distance` returns SRS units (degrees for
  4326) instead of meters. Use `geography` columns for meters.

Unsupported drivers (e.g. SQLite) throw
`Juanparati\LaraGeos\Exceptions\UnsupportedDriverException`.

## GeoJSON

Both types convert to and from RFC 7946 GeoJSON geometries (coordinates are
`[lng, lat]`; GeoJSON is always WGS 84, so no SRID is emitted):

```php
$point = Point::fromGeoJson('{"type":"Point","coordinates":[39.1234,27.1234]}');
$point->toGeoJson();   // ['type' => 'Point', 'coordinates' => [39.1234, 27.1234]]
json_encode($point);   // {"type":"Point","coordinates":[39.1234,27.1234]}

$polygon = Polygon::fromGeoJson([
    'type'        => 'Polygon',
    'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 0]]],
]);
json_encode($polygon); // {"type":"Polygon","coordinates":[[[0,0],[4,0],[4,4],[0,0]]]}
```

WKT factories are also available: `Point::fromWkt()` / `Polygon::fromWkt()`.

## Model serialization

`toArray()` / `toJson()` on a model serialize spatial attributes as:

```php
// Point
['lat' => 27.1234, 'lng' => 39.1234, 'srid' => 4326]

// Polygon
['rings' => [[['lat' => ..., 'lng' => ...], ...], ...], 'srid' => 4326]
```

## Testing

```bash
composer test
```

The CI matrix runs the suite on PHP 8.2–8.5 against MySQL 8.4, MariaDB 11.4,
and PostGIS 16.

## License

MIT. See [LICENSE](LICENSE).
