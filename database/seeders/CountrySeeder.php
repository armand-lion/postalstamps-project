<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'name' => 'Nederland',
                'iso_code' => 'NL',
                'slug' => 'nederland',
            ],
            [
                'name' => 'België',
                'iso_code' => 'BE',
                'slug' => 'belgie',
            ],
            [
                'name' => 'Duitsland',
                'iso_code' => 'DE',
                'slug' => 'duitsland',
            ],
            [
                'name' => 'Frankrijk',
                'iso_code' => 'FR',
                'slug' => 'frankrijk',
            ],
            [
                'name' => 'Verenigd Koninkrijk',
                'iso_code' => 'GB',
                'slug' => 'verenigd-koninkrijk',
            ],
            [
                'name' => 'Verenigde Staten',
                'iso_code' => 'US',
                'slug' => 'verenigde-staten',
            ],
            [
                'name' => 'DDR',
                'iso_code' => 'DD',
                'slug' => 'ddr',
            ],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['slug' => $country['slug']],
                $country
            );
        }
    }
}
