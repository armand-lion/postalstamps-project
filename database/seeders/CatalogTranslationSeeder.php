<?php

namespace Database\Seeders;

use App\Models\CountryTranslation;
use App\Models\StampSeriesTranslation;
use App\Models\StampTranslation;
use Illuminate\Database\Seeder;

class CatalogTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * Country translations
         */
        $countryTranslations = [
            1 => [
                'en' => ['name' => 'Netherlands', 'description' => null],
                'de' => ['name' => 'Niederlande', 'description' => null],
                'fr' => ['name' => 'Pays-Bas', 'description' => null],
                'es' => ['name' => 'Países Bajos', 'description' => null],
            ],
            2 => [
                'en' => ['name' => 'Belgium', 'description' => null],
                'de' => ['name' => 'Belgien', 'description' => null],
                'fr' => ['name' => 'Belgique', 'description' => null],
                'es' => ['name' => 'Bélgica', 'description' => null],
            ],
            3 => [
                'en' => ['name' => 'Germany', 'description' => null],
                'de' => ['name' => 'Deutschland', 'description' => null],
                'fr' => ['name' => 'Allemagne', 'description' => null],
                'es' => ['name' => 'Alemania', 'description' => null],
            ],
            4 => [
                'en' => ['name' => 'France', 'description' => null],
                'de' => ['name' => 'Frankreich', 'description' => null],
                'fr' => ['name' => 'France', 'description' => null],
                'es' => ['name' => 'Francia', 'description' => null],
            ],
            5 => [
                'en' => ['name' => 'United Kingdom', 'description' => null],
                'de' => ['name' => 'Vereinigtes Königreich', 'description' => null],
                'fr' => ['name' => 'Royaume-Uni', 'description' => null],
                'es' => ['name' => 'Reino Unido', 'description' => null],
            ],
            6 => [
                'en' => ['name' => 'United States', 'description' => null],
                'de' => ['name' => 'Vereinigte Staaten', 'description' => null],
                'fr' => ['name' => 'États-Unis', 'description' => null],
                'es' => ['name' => 'Estados Unidos', 'description' => null],
            ],
            7 => [
                'en' => ['name' => 'East Germany (DDR)', 'description' => null],
                'de' => ['name' => 'DDR', 'description' => null],
                'fr' => ['name' => 'Allemagne de l’Est (RDA)', 'description' => null],
                'es' => ['name' => 'Alemania Oriental (RDA)', 'description' => null],
            ],
        ];

        foreach ($countryTranslations as $countryId => $locales) {
            foreach ($locales as $locale => $data) {
                CountryTranslation::firstOrCreate(
                    [
                        'country_id' => $countryId,
                        'locale' => $locale,
                    ],
                    $data
                );
            }
        }

        /*
         * Stamp translations
         */
        $stampTranslations = [
            3 => [
                'en' => [
                    'name' => 'Test stamp',
                    'description' => 'Test stamp — modification test',
                ],
                'de' => [
                    'name' => 'Testbriefmarke',
                    'description' => 'Testbriefmarke — Änderungstest',
                ],
                'fr' => [
                    'name' => 'Timbre de test',
                    'description' => 'Timbre de test — test de modification',
                ],
                'es' => [
                    'name' => 'Sello de prueba',
                    'description' => 'Sello de prueba — prueba de modificación',
                ],
            ],
            4 => [
                'en' => [
                    'name' => 'Belgian stamp',
                    'description' => null,
                ],
                'de' => [
                    'name' => 'Belgische Briefmarke',
                    'description' => null,
                ],
                'fr' => [
                    'name' => 'Timbre belge',
                    'description' => null,
                ],
                'es' => [
                    'name' => 'Sello belga',
                    'description' => null,
                ],
            ],
        ];

        foreach ($stampTranslations as $stampId => $locales) {
            foreach ($locales as $locale => $data) {
                StampTranslation::firstOrCreate(
                    [
                        'stamp_id' => $stampId,
                        'locale' => $locale,
                    ],
                    $data
                );
            }
        }

        /*
         * Stamp series translations
         */
        $seriesTranslations = [
            2 => [
                'en' => [
                    'name' => 'Test security series',
                    'description' => 'Test for protecting related data.',
                ],
                'de' => [
                    'name' => 'Testserie zum Datenschutz',
                    'description' => 'Test zum Schutz verknüpfter Daten.',
                ],
                'fr' => [
                    'name' => 'Série de test de sécurité',
                    'description' => 'Test de protection des données associées.',
                ],
                'es' => [
                    'name' => 'Serie de prueba de seguridad',
                    'description' => 'Prueba para proteger los datos relacionados.',
                ],
            ],
            3 => [
                'en' => [
                    'name' => 'Belgian series',
                    'description' => null,
                ],
                'de' => [
                    'name' => 'Belgische Serie',
                    'description' => null,
                ],
                'fr' => [
                    'name' => 'Série belge',
                    'description' => null,
                ],
                'es' => [
                    'name' => 'Serie belga',
                    'description' => null,
                ],
            ],
        ];

        foreach ($seriesTranslations as $seriesId => $locales) {
            foreach ($locales as $locale => $data) {
                StampSeriesTranslation::firstOrCreate(
                    [
                        'stamp_series_id' => $seriesId,
                        'locale' => $locale,
                    ],
                    $data
                );
            }
        }
    }
}
