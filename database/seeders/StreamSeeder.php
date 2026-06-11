<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Stream;

class StreamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Science', 'Commerce', 'Humanities'] as $stream) {
            Stream::firstOrCreate([
                'name' => $stream,
            ], [
                'is_active' => true,
            ]);
        }
    }
}
