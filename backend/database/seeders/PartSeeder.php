<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parts = json_decode(file_get_contents(__DIR__ . '/parts.json'), true, flags: JSON_THROW_ON_ERROR);

        Part::unguarded(function() use ($parts) {
            foreach ($parts as $part) {
                Part::create([
                    'id'        => (int) $part['id'],
                    'name'      => $part['name'],
                    'oem'       => $part['oem'],
                    'brand'     => $part['brand'],
                    'condition' => $part['condition'],
                    'quantity'  => $part['stockQuantity'],
                    'price'     => $part['price'],
                    'image'     => $part['image'],
                    'category'  => $part['category'],
                    'colours'   => $part['colours'],
                ]);
            }
        });
    }
}
