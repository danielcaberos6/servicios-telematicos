<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $script = file_get_contents(database_path('sql/Script.sql'));
        $seed = explode('-- BEGIN SEMILLAS PREDETERMINADAS', $script)[1];
        DB::unprepared(explode('-- END SEMILLAS PREDETERMINADAS', $seed)[0]);
    }
}
