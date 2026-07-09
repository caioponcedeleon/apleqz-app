<?php

namespace App\Support;

class AiUsageContext
{
    /**
     * @var array{user_id?: int, purpose?: string}|null
     */
    protected static ?array $context = null;

    /**
     * @param  array{user_id?: int, purpose?: string}  $context
     */
    public static function run(array $context, callable $callback): mixed
    {
        $previous = static::$context;
        static::$context = $context;

        try {
            return $callback();
        } finally {
            static::$context = $previous;
        }
    }

    /**
     * @return array{user_id?: int, purpose?: string}
     */
    public static function current(): array
    {
        return static::$context ?? [];
    }
}
