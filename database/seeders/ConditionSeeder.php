<?php

namespace Database\Seeders;

use App\Models\Condition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            [
                'name' => 'Postfris',
                'description' => 'Ongebruikt, met originele gom en zonder plakker.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Ongebruikt met plakker',
                'description' => 'Ongebruikt exemplaar met plakker of plakkerrest.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Ongebruikt zonder gom',
                'description' => 'Ongebruikt exemplaar zonder originele gom.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Gestempeld',
                'description' => 'Gebruikt exemplaar met een stempel.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Licht gestempeld',
                'description' => 'Gebruikt exemplaar met een lichte stempel.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Beschadigd',
                'description' => 'Exemplaar met schade, zoals scheuren of ontbrekende tandjes.',
                'sort_order' => 6,
            ],
        ];

        foreach ($conditions as $condition) {
            Condition::updateOrCreate(
                ['slug' => Str::slug($condition['name'])],
                [
                    ...$condition,
                    'is_active' => true,
                ]
            );
        }
    }
}
