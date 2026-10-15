<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            'Dieren',
            'Natuur',
            'Planten en bloemen',
            'Sport',
            'Kunst',
            'Architectuur',
            'Geschiedenis',
            'Koningshuizen',
            'Vervoer',
            'Ruimtevaart',
            'Wetenschap',
            'Cultuur',
            'Religie',
            'Beroemde personen',
            'Gebeurtenissen',
        ];

        foreach ($themes as $name) {
            Theme::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                ]
            );
        }
    }
}
