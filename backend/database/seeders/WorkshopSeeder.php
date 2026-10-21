<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Workshop;
use Illuminate\Database\Seeder;

class WorkshopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fixed tokens so the frontend can be configured with a known one
        Workshop::factory()->has(Quote::factory()->count(2))->create([
            'name' => 'Workshop A',
            'token' => 'Bo0NmQKBkj',
            'discount' => 0,
        ]);
        Workshop::factory()->has(Quote::factory()->count(2))->create([
            'name' => 'Workshop B',
            'token' => 'Xk7pLq2Rm9',
            'discount' => 10,
        ]);
    }
}
