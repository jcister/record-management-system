<?php

namespace App\Support;

class CoaContactDefaults
{
    public const POSITION = 'State Auditor IV';

    public const DESIGNATION = 'Team Leader';

    public static function position(): string
    {
        return self::POSITION;
    }

    public static function designation(): string
    {
        return self::DESIGNATION;
    }
}
