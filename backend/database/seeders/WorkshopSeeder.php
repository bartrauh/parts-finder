<?php

namespace Database\Seeders;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class WorkshopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workshop::factory()->count(2)->state(new Sequence(['discount' => 0], ['discount' => 10]))->create();
    }
}
