<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Types;

use InvalidArgumentException;
use JsonSerializable;

class Polygon implements JsonSerializable
{
    /** @var Point[][] Closed linear rings; index 0 is the exterior ring, the rest are holes. */
    protected array $rings;

    protected int $srid;

    /**
     * @param  Point[]|Point[][]  $rings  Either a flat list of points (the exterior ring)
     *                                    or a list of rings: [exterior, ...holes].
     */
    public function __construct(array $rings, ?int $srid = 4326)
    {
        if ($rings === []) {
            throw new InvalidArgumentException('A polygon must have at least one ring.');
        }

        $rings = array_values($rings);

        if ($rings[0] instanceof Point) {
            $rings = [$rings];
        }

        $this->rings = array_map($this->normalizeRing(...), $rings);
        $this->srid = $srid ?? 0;
    }

    public static function fromWkt(string $wkt, ?int $srid = null): static
    {
        if (preg_match('/^\s*POLYGON\s*\(\s*(\(.*\))\s*\)\s*$/is', $wkt, $matches) !== 1
            || preg_match_all('/\(([^()]+)\)/', $matches[1], $ringMatches) < 1
        ) {
            throw new InvalidArgumentException("The given WKT is not a valid POLYGON: {$wkt}");
        }

        $rings = array_map(
            fn (string $body) => array_map(function (string $pair) {
                $coords = preg_split('/\s+/', trim($pair));

                return new Point(lat: (float) $coords[1], lng: (float) $coords[0], srid: null);
            }, explode(',', $body)),
            $ringMatches[1]
        );

        return new static($rings, $srid);
    }

    /**
     * Accepts an RFC 7946 GeoJSON Polygon geometry, as an array or a JSON string.
     * GeoJSON coordinates are rings of [longitude, latitude] positions.
     */
    public static function fromGeoJson(array|string $geoJson, ?int $srid = 4326): static
    {
        if (is_string($geoJson)) {
            $geoJson = json_decode($geoJson, associative: true, flags: JSON_THROW_ON_ERROR);
        }

        if (($geoJson['type'] ?? null) !== 'Polygon' || !is_array($geoJson['coordinates'] ?? null) || $geoJson['coordinates'] === []) {
            throw new InvalidArgumentException('The given GeoJSON is not a valid Polygon geometry.');
        }

        $rings = array_map(
            fn (array $ring) => array_map(
                fn (array $position) => new Point(lat: (float) $position[1], lng: (float) $position[0], srid: null),
                $ring
            ),
            $geoJson['coordinates']
        );

        return new static($rings, $srid);
    }

    /**
     * @return Point[][] All closed rings; index 0 is the exterior ring.
     */
    public function getRings(): array
    {
        return $this->rings;
    }

    /**
     * @return Point[]
     */
    public function getExteriorRing(): array
    {
        return $this->rings[0];
    }

    /**
     * @return Point[][]
     */
    public function getInteriorRings(): array
    {
        return array_slice($this->rings, 1);
    }

    /**
     * @return Point[] The exterior ring.
     */
    public function getPoints(): array
    {
        return $this->getExteriorRing();
    }

    public function getSrid(): int
    {
        return $this->srid;
    }

    /**
     * Coordinate pairs of the exterior ring.
     */
    public function toPairs(): string
    {
        return $this->ringToPairs($this->getExteriorRing());
    }

    public function toWkt(): string
    {
        $rings = implode('),(', array_map($this->ringToPairs(...), $this->rings));

        return sprintf('POLYGON((%s))', $rings);
    }

    public function toArray(): array
    {
        return [
            'rings' => array_map(
                fn (array $ring) => array_map(fn (Point $point) => [
                    'lat' => $point->getLat(),
                    'lng' => $point->getLng(),
                ], $ring),
                $this->rings
            ),
            'srid' => $this->srid,
        ];
    }

    /**
     * RFC 7946 GeoJSON geometry. GeoJSON is always WGS 84, so the SRID is not emitted.
     */
    public function toGeoJson(): array
    {
        return [
            'type'        => 'Polygon',
            'coordinates' => array_map(
                fn (array $ring) => array_map(fn (Point $point) => [$point->getLng(), $point->getLat()], $ring),
                $this->rings
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toGeoJson();
    }

    /**
     * @param  Point[]  $ring
     * @return Point[] The validated, closed ring.
     */
    private function normalizeRing(mixed $ring): array
    {
        if (!is_array($ring) || $ring === []) {
            throw new InvalidArgumentException('A polygon ring must be a non-empty array of points.');
        }

        foreach ($ring as $point) {
            if (!$point instanceof Point) {
                throw new InvalidArgumentException('All points must be instances of ' . Point::class);
            }
        }

        $ring = array_values($ring);
        $first = $ring[0];
        $last = end($ring);

        $isClosed = $first->getLat() === $last->getLat() && $first->getLng() === $last->getLng();

        $unique = [];

        foreach ($isClosed ? array_slice($ring, 0, -1) : $ring as $point) {
            $unique[$point->toPair()] = true;
        }

        if (count($unique) < 3) {
            throw new InvalidArgumentException('A polygon ring must have at least 3 unique points.');
        }

        if (!$isClosed) {
            $ring[] = clone $first;
        }

        return $ring;
    }

    /**
     * @param  Point[]  $ring
     */
    private function ringToPairs(array $ring): string
    {
        return implode(',', array_map(fn (Point $point) => $point->toPair(), $ring));
    }
}
