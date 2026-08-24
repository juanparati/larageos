<?php

declare(strict_types=1);

namespace Juanparati\LaraGeos\Exceptions;

use RuntimeException;

final class UnsupportedDriverException extends RuntimeException
{
    public static function make(string $driver): self
    {
        return new self(sprintf(
            'The [%s] database driver is not supported. Supported drivers: mysql, mariadb, pgsql.',
            $driver
        ));
    }
}
