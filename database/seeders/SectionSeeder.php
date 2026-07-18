<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Grade::all() as $grade) {

            foreach (['A', 'B'] as $section) {

                Section::firstOrCreate([
                    'grade_id' => $grade->id,
                    'stream_id' => null,
                    'name' => $section,
                ], [
                    'display_name' => 'Section '.$section,
                    'capacity' => 50,
                    'is_active' => true,
                ]);
            }
        }
    }
}