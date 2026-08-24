<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Casts;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Juanparati\LaraGeos\Casts\Contracts\LocationCastContract;
use Juanparati\LaraGeos\Support\GeometryExpression;
use Juanparati\LaraGeos\Support\WkbParser;
use Juanparati\LaraGeos\Types\Point;

class LocationCast implements LocationCastContract
{
    public function get($model, string $key, $value, array $attributes): ?Point
    {
        if (is_null($value)) {
            return null;
        }

        // Eloquent hands back the Expression produced by set() on paths that
        // bypass the class cast cache (replicate, getOriginal, discardChanges).
        if ($value instanceof Expression) {
            [$wkt, $srid] = GeometryExpression::extractWkt($value, $model->getConnection()->getQueryGrammar());

            return Point::fromWkt($wkt, $srid);
        }

        return WkbParser::parsePoint($value, $model->getConnection()->getDriverName());
    }

    public function set($model, string $key, $value, array $attributes): Expression
    {
        if (!$value instanceof Point) {
            throw new InvalidArgumentException(
                sprintf('The %s field must be instance of %s', $key, Point::class)
            );
        }

        $connection = $model->getConnection();

        return $connection->raw(GeometryExpression::geomFromText($value, $connection->getDriverName()));
    }

    public function serialize($model, string $key, $value, array $attributes): array
    {
        return $value->toArray();
    }
}
