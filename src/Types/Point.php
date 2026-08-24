<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Types;

use InvalidArgumentException;
use JsonSerializable;

class Point implements JsonSerializable
{
    protected float $lat;

    protected float $lng;

    protected int $srid;

    public function __construct(float $lat = 0, float $lng = 0, ?int $srid = 4326)
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException("The latitude must be between -90 and 90, {$lat} given.");
        }

        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException("The longitude must be between -180 and 180, {$lng} given.");
        }

        $this->lat = $lat;
        $this->lng = $lng;
        $this->srid = $srid ?? 0;
    }

    public static function fromWkt(string $wkt, ?int $srid = null): static
    {
        if (preg_match('/^\s*POINT\s*\(\s*(-?[\d.]+)\s+(-?[\d.]+)\s*\)\s*$/i', $wkt, $matches) !== 1) {
            throw new InvalidArgumentException("The given WKT is not a valid POINT: {$wkt}");
        }

        return new static(lat: (float) $matches[2], lng: (float) $matches[1], srid: $srid);
    }

    /**
     * Accepts an RFC 7946 GeoJSON Point geometry, as an array or a JSON string.
     * GeoJSON coordinates are [longitude, latitude].
     */
    public static function fromGeoJson(array|string $geoJson, ?int $srid = 4326): static
    {
        if (is_string($geoJson)) {
            $geoJson = json_decode($geoJson, associative: true, flags: JSON_THROW_ON_ERROR);
        }

        if (($geoJson['type'] ?? null) !== 'Point' || !is_array($geoJson['coordinates'] ?? null) || count($geoJson['coordinates']) < 2) {
            throw new InvalidArgumentException('The given GeoJSON is not a valid Point geometry.');
        }

        [$lng, $lat] = $geoJson['coordinates'];

        return new static(lat: (float) $lat, lng: (float) $lng, srid: $srid);
    }

    public function getLat(): float
    {
        return $this->lat;
    }

    public function getLng(): float
    {
        return $this->lng;
    }

    public function getSrid(): int
    {
        return $this->srid;
    }

    public function toWkt(): string
    {
        return sprintf('POINT(%s)', $this->toPair());
    }

    public function toPair(): string
    {
        return "{$this->getLng()} {$this->getLat()}";
    }

    public function toArray(): array
    {
        return [
            'lat'  => $this->lat,
            'lng'  => $this->lng,
            'srid' => $this->srid,
        ];
    }

    /**
     * RFC 7946 GeoJSON geometry. GeoJSON is always WGS 84, so the SRID is not emitted.
     */
    public function toGeoJson(): array
    {
        return [
            'type'        => 'Point',
            'coordinates' => [$this->lng, $this->lat],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toGeoJson();
    }
}
