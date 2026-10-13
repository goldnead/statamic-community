<?php

namespace Goldnead\Community\Seeders;

use Goldnead\Community\Services\RoomService;
use Illuminate\Database\Seeder;

/** Curated rooms from `community.rooms`; run it as often as you like. */
class RoomSeeder extends Seeder
{
    public function run(): void
    {
        app(RoomService::class)->syncCurated();
    }
}
