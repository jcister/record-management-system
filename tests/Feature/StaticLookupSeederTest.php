<?php

use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds lookup tables without creating duplicates on rerun', function () {
    $this->seed(DepartmentSeeder::class);
    $this->seed(UnitSeeder::class);
    $this->seed(PositionSeeder::class);

    $departmentCount = DB::table('departments')->count();
    $unitCount = DB::table('units')->count();
    $positionCount = DB::table('positions')->count();

    $this->seed(DepartmentSeeder::class);
    $this->seed(UnitSeeder::class);
    $this->seed(PositionSeeder::class);

    expect(DB::table('departments')->count())->toBe($departmentCount)
        ->and(DB::table('units')->count())->toBe($unitCount)
        ->and(DB::table('positions')->count())->toBe($positionCount)
        ->and($departmentCount)->toBe(2)
        ->and($unitCount)->toBe(22)
        ->and($positionCount)->toBe(23);
});
