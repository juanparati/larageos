<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Support;

use InvalidArgumentException;
use Juanparati\LaraGeos\Types\Point;
use Juanparati\LaraGeos\Types\Polygon;

/**
 * Parses geometry column values: the MySQL/MariaDB internal format (4-byte SRID
 * prefix + WKB), plain WKB, and PostGIS (E)WKB, including hex encoding.
 *
 * All of these store x = longitude first. MySQL's lat-first SRS axis order for
 * geographic SRIDs applies only to its WKT/WKB conversion functions (hence the
 * 'axis-order=long-lat' option on writes), not to the stored column bytes
 * (verified against MySQL 8.4).
 */
final class WkbParser
{
    private const TYPE_POINT = 1;

    private const TYPE_POLYGON = 3;

    private const EWKB_SRID_FLAG = 0x20000000;

    private const EWKB_ZM_FLAGS = 0xC0000000;

    public static function isWkb(mixed $value): bool
    {
        if (is_resource($value)) {
            return get_resource_type($value) === 'stream';
        }

        if (!is_string($value) || $value === '' || str_contains($value, 'POINT(') || str_contains($value, 'POLYGON(')) {
            return false;
        }

        $binary = self::toBinary($value);

        return !is_null(self::readInternalHeader($binary)) || !is_null(self::readWkbHeader($binary));
    }

    public static function parsePoint(mixed $value, string $driver): Point
    {
        $binary = self::toBinary($value);

        [$srid, $littleEndian, $offset] = self::readHeader($binary, $driver, self::TYPE_POINT);

        [$x, $y, $offset] = self::readCoordinates($binary, $littleEndian, $offset);

        self::assertFullyConsumed($binary, $offset);

        return new Point(lat: $y, lng: $x, srid: $srid);
    }

    public static function parsePolygon(mixed $value, string $driver): Polygon
    {
        $binary = self::toBinary($value);

        [$srid, $littleEndian, $offset] = self::readHeader($binary, $driver, self::TYPE_POLYGON);

        $ringCount = self::readUint32($binary, $littleEndian, $offset);
        $offset += 4;

        if ($ringCount < 1) {
            throw new InvalidArgumentException('The WKB polygon does not contain any rings.');
        }

        $rings = [];

        for ($ring = 0; $ring < $ringCount; $ring++) {
            $pointCount = self::readUint32($binary, $littleEndian, $offset);
            $offset += 4;

            $points = [];

            for ($i = 0; $i < $pointCount; $i++) {
                [$x, $y, $offset] = self::readCoordinates($binary, $littleEndian, $offset);

                $points[] = new Point(lat: $y, lng: $x, srid: null);
            }

            $rings[] = $points;
        }

        self::assertFullyConsumed($binary, $offset);

        return new Polygon($rings, $srid > 0 ? $srid : null);
    }

    private static function toBinary(mixed $value): string
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('The given value is not a valid WKB geometry.');
        }

        // PostgreSQL/PostGIS returns geometry columns as hex-encoded EWKB.
        if (strlen($value) >= 42 && strlen($value) % 2 === 0 && ctype_xdigit($value)) {
            return hex2bin($value);
        }

        return $value;
    }

    /**
     * @return array{int, bool, int} [srid, littleEndian, offset]
     */
    private static function readHeader(string $binary, string $driver, int $expectedType): array
    {
        $header = $driver === 'pgsql'
            ? (self::readWkbHeader($binary) ?? self::readInternalHeader($binary))
            : (self::readInternalHeader($binary) ?? self::readWkbHeader($binary));

        if (is_null($header)) {
            throw new InvalidArgumentException('The given value is not a valid WKB geometry.');
        }

        [$srid, $littleEndian, $type, $offset, $hasZM] = $header;

        if ($hasZM) {
            throw new InvalidArgumentException('WKB geometries with Z or M dimensions are not supported.');
        }

        if ($type !== $expectedType) {
            throw new InvalidArgumentException(sprintf(
                'Unexpected WKB geometry type: expected %s.',
                $expectedType === self::TYPE_POINT ? 'POINT' : 'POLYGON'
            ));
        }

        return [$srid, $littleEndian, $offset];
    }

    /**
     * MySQL and MariaDB internal column format: 4-byte little-endian SRID prefix + WKB.
     *
     * @return array{int, bool, int, int, bool}|null [srid, littleEndian, type, offset, hasZM]
     */
    private static function readInternalHeader(string $binary): ?array
    {
        if (strlen($binary) < 9) {
            return null;
        }

        $byteOrder = ord($binary[4]);

        if ($byteOrder > 1) {
            return null;
        }

        $littleEndian = $byteOrder === 1;

        $type = unpack($littleEndian ? 'V' : 'N', $binary, 5)[1];

        if (!in_array($type, [self::TYPE_POINT, self::TYPE_POLYGON], true)) {
            return null;
        }

        return [unpack('V', $binary)[1], $littleEndian, $type, 9, false];
    }

    /**
     * Plain WKB or PostGIS EWKB (optional embedded SRID, Z/M flags).
     *
     * @return array{int, bool, int, int, bool}|null [srid, littleEndian, type, offset, hasZM]
     */
    private static function readWkbHeader(string $binary): ?array
    {
        if (strlen($binary) < 5) {
            return null;
        }

        $byteOrder = ord($binary[0]);

        if ($byteOrder > 1) {
            return null;
        }

        $littleEndian = $byteOrder === 1;

        $type = unpack($littleEndian ? 'V' : 'N', $binary, 1)[1];

        $hasZM = ($type & self::EWKB_ZM_FLAGS) !== 0;

        $srid = 0;
        $offset = 5;

        if (($type & self::EWKB_SRID_FLAG) !== 0) {
            if (strlen($binary) < 9) {
                return null;
            }

            $srid = unpack($littleEndian ? 'V' : 'N', $binary, 5)[1];
            $offset = 9;
        }

        $type &= ~(self::EWKB_SRID_FLAG | self::EWKB_ZM_FLAGS);

        // ISO WKB encodes Z/M/ZM variants as type offsets of 1000/2000/3000.
        $hasZM = $hasZM || intdiv($type, 1000) > 0;
        $type %= 1000;

        if (!in_array($type, [self::TYPE_POINT, self::TYPE_POLYGON], true)) {
            return null;
        }

        return [$srid, $littleEndian, $type, $offset, $hasZM];
    }

    private static function readUint32(string $binary, bool $littleEndian, int $offset): int
    {
        if (strlen($binary) < $offset + 4) {
            throw new InvalidArgumentException('The WKB geometry is truncated.');
        }

        return unpack($littleEndian ? 'V' : 'N', $binary, $offset)[1];
    }

    /**
     * @return array{float, float, int} [x, y, offset]
     */
    private static function readCoordinates(string $binary, bool $littleEndian, int $offset): array
    {
        if (strlen($binary) < $offset + 16) {
            throw new InvalidArgumentException('The WKB geometry is truncated.');
        }

        return [
            unpack($littleEndian ? 'e' : 'E', $binary, $offset)[1],
            unpack($littleEndian ? 'e' : 'E', $binary, $offset + 8)[1],
            $offset + 16,
        ];
    }

    private static function assertFullyConsumed(string $binary, int $offset): void
    {
        if (strlen($binary) !== $offset) {
            throw new InvalidArgumentException('The WKB geometry is truncated or contains trailing bytes.');
        }
    }
}
