<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Direction;
use App\Models\Region_groub;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegionGroubFactory extends Factory
{
    protected $model = Region_groub::class;

    public function definition(): array
    {
        $parent = Branch::query()
            ->where('is_territory', false)
            ->whereNull('parent_id')
            ->first();

        if (!$parent) {
            $parent = Branch::create([
                'name' => 'Test HQ',
                'code' => 'HQ-' . fake()->unique()->numerify('######'),
                'phone' => '0110000000',
                'mobile' => '0500000000',
                'address1' => '-',
                'city' => 'Riyadh',
                'country' => 'SA',
                'status' => 0,
                'is_main' => true,
            ]);
        }

        return [
            'name' => fake()->unique()->words(2, true),
            'parent_id' => $parent->id,
            'territory_office_id' => $parent->id,
            'directions_id' => Direction::query()->value('id'),
            'is_territory' => true,
            'code' => 'TR-' . fake()->unique()->numerify('########'),
            'phone' => $parent->phone ?? '0',
            'mobile' => $parent->mobile ?? '0',
            'address1' => '-',
            'city' => $parent->city ?? 'Riyadh',
            'country' => $parent->country ?? 'SA',
            'status' => 0,
            'is_main' => false,
        ];
    }
}
