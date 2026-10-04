<?php

namespace Insane\Agreements;

use Insane\Agreements\Contracts\ContractType;
use InvalidArgumentException;

/** Registry of contract types (config agreements.types). */
class Agreements
{
    /** @return array<string, ContractType> */
    public static function types(): array
    {
        $types = [];
        foreach (config('agreements.types', []) as $key => $class) {
            $types[$key] = app($class);
        }

        return $types;
    }

    public static function type(string $key): ContractType
    {
        $class = config("agreements.types.$key");
        if (! $class) {
            throw new InvalidArgumentException("Unknown contract type [$key]. Register it in config/agreements.php.");
        }

        return app($class);
    }

    public static function clientModel(): string
    {
        return config('agreements.client_model');
    }
}
