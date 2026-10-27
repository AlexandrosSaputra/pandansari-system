<?php

namespace Database\Factories;

use App\Models\HistoricalSale;
use App\Models\Item;
use App\Models\OperationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricalSale>
 */
class HistoricalSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = fake()->dateTimeBetween('-4 years', '-1 week')->modify('monday this week');
        $units = fake()->numberBetween(1, 100);
        $price = fake()->randomElement([2500, 3000, 4000, 5000, 7000, 8000, 10000]);

        return [
            'operational_unit_id' => fn (): int => OperationalUnit::query()->firstOrFail()->id,
            'item_id' => fn (): int => Item::query()->products()->firstOrFail()->id,
            'week_id' => fake()->unique()->bothify('W###'),
            'week_start' => $weekStart,
            'week_end' => (clone $weekStart)->modify('+6 days'),
            'source_outlet' => 'Factory Outlet',
            'source_sku' => fn (array $attributes): string => Item::query()->findOrFail($attributes['item_id'])->name,
            'units_sold' => $units,
            'unit_price' => $price,
            'sales_value' => $units * $price,
            'source_file' => 'factory.csv',
            'source_row' => fake()->unique()->numberBetween(2, 100000),
        ];
    }
}
