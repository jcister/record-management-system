<?php

namespace App\Support;

class RetentionStatus
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public const READY_FOR_DISPOSAL = 'ready_for_disposal';

    public const DISPOSED = 'disposed';

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            self::ACTIVE,
            self::READY_FOR_DISPOSAL,
            self::DISPOSED,
        ];
    }
}
