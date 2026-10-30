<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Watermark;
use App\Models\WatermarkTranslation;
use Illuminate\Database\Seeder;

class WatermarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $watermarks = [
            'NL' => [
                'posthoorn' => [
                    'sort_order' => 10,
                    'translations' => [
                        'nl' => 'Posthoorn',
                        'en' => 'Post horn',
                        'de' => 'Posthorn',
                        'fr' => 'Cor de poste',
                        'es' => 'Corneta postal',
                    ],
                ],
                'ringen' => [
                    'sort_order' => 20,
                    'translations' => [
                        'nl' => 'Ringen',
                        'en' => 'Rings',
                        'de' => 'Ringe',
                        'fr' => 'Anneaux',
                        'es' => 'Anillos',
                    ],
                ],
            ],
            'BE' => [
                // Belgische watermerken met vijf vertalingen.
            ],
            'DE' => [
                'rauten' => [
                    'sort_order' => 10,
                    'translations' => [
                        'nl' => 'Ruiten',
                        'en' => 'Lozenges',
                        'de' => 'Rauten',
                        'fr' => 'Losanges',
                        'es' => 'Rombos',
                    ],
                ],
                'waffeln' => [
                    'sort_order' => 20,
                    'translations' => [
                        'nl' => 'Wafelpatroon',
                        'en' => 'Waffle pattern',
                        'de' => 'Waffeln',
                        'fr' => 'Motif gaufré',
                        'es' => 'Patrón de gofre',
                    ],
                ],
                'kreuzblueten' => [
                    'sort_order' => 30,
                    'translations' => [
                        'nl' => 'Kruisbloemen',
                        'en' => 'Quatrefoils',
                        'de' => 'Kreuzblüten',
                        'fr' => 'Quatre-feuilles',
                        'es' => 'Cuatrifolios',
                    ],
                ],
            ],
            'DD' => [
                'kreuzblumen' => [
                    'sort_order' => 10,
                    'translations' => [
                        'nl' => 'Kruisbloemen',
                        'en' => 'Cross flowers',
                        'de' => 'Kreuzblumen',
                        'fr' => 'Fleurs en croix',
                        'es' => 'Flores en cruz',
                    ],
                ],
                'posthorn-und-ddr' => [
                    'sort_order' => 20,
                    'translations' => [
                        'nl' => 'Posthoorn en DDR',
                        'en' => 'Post horn and DDR',
                        'de' => 'Posthorn und DDR',
                        'fr' => 'Cor de poste et RDA',
                        'es' => 'Corneta postal y RDA',
                    ],
                ],
                'kreuzblumen-und-ddr' => [
                    'sort_order' => 30,
                    'translations' => [
                        'nl' => 'Kruisbloemen en DDR',
                        'en' => 'Cross flowers and GDR',
                        'de' => 'Kreuzblumen und DDR',
                        'fr' => 'Fleurs en croix et RDA',
                        'es' => 'Flores en cruz y RDA',
                    ],
                ],
            ],
            'GB' => [
                'small-crown' => [
                    'sort_order' => 10,
                    'translations' => [
                        'nl' => 'Kleine kroon',
                        'en' => 'Small Crown',
                        'de' => 'Kleine Krone',
                        'fr' => 'Petite couronne',
                        'es' => 'Corona pequeña',
                    ],
                ],

                'large-crown' => [
                    'sort_order' => 20,
                    'translations' => [
                        'nl' => 'Grote kroon',
                        'en' => 'Large Crown',
                        'de' => 'Große Krone',
                        'fr' => 'Grande couronne',
                        'es' => 'Corona grande',
                    ],
                ],

                'multiple-crowns' => [
                    'sort_order' => 30,
                    'translations' => [
                        'nl' => 'Meerdere kronen',
                        'en' => 'Multiple Crowns',
                        'de' => 'Mehrere Kronen',
                        'fr' => 'Couronnes multiples',
                        'es' => 'Coronas múltiples',
                    ],
                ],
            ],
            'US' => [
                'double-line-usps' => [
                    'sort_order' => 10,
                    'translations' => [
                        'nl' => 'USPS met dubbele lijnen',
                        'en' => 'Double-line USPS',
                        'de' => 'USPS mit Doppellinien',
                        'fr' => 'USPS à doubles lignes',
                        'es' => 'USPS de líneas dobles',
                    ],
                ],

                'single-line-usps' => [
                    'sort_order' => 20,
                    'translations' => [
                        'nl' => 'USPS met enkele lijnen',
                        'en' => 'Single-line USPS',
                        'de' => 'USPS mit einfachen Linien',
                        'fr' => 'USPS à lignes simples',
                        'es' => 'USPS de líneas simples',
                    ],
                ],
            ],
        ];

        foreach ($watermarks as $countryCode => $countryWatermarks) {
            $country = Country::where('iso_code', $countryCode)->first();

            if (! $country) {
                continue;
            }

            foreach ($countryWatermarks as $slug => $data) {
                $watermark = Watermark::updateOrCreate(
                    [
                        'country_id' => $country->id,
                        'slug' => $slug,
                    ],
                    [
                        'sort_order' => $data['sort_order'],
                        'is_active' => true,
                    ]
                );

                foreach ($data['translations'] as $locale => $name) {
                    WatermarkTranslation::updateOrCreate(
                        [
                            'watermark_id' => $watermark->id,
                            'locale' => $locale,
                        ],
                        [
                            'name' => $name,
                        ]
                    );
                }
            }
        }
    }
}
