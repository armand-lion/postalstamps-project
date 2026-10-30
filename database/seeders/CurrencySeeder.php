<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'EUR',
                'sort_order' => 1,
                'translations' => [
                    'nl' => 'Euro',
                    'en' => 'Euro',
                    'de' => 'Euro',
                    'fr' => 'Euro',
                    'es' => 'Euro',
                ],
            ],
            [
                'code' => 'USD',
                'sort_order' => 2,
                'translations' => [
                    'nl' => 'Amerikaanse dollar',
                    'en' => 'US dollar',
                    'de' => 'US-Dollar',
                    'fr' => 'Dollar américain',
                    'es' => 'Dólar estadounidense',
                ],
            ],
            [
                'code' => 'GBP',
                'sort_order' => 3,
                'translations' => [
                    'nl' => 'Britse pond',
                    'en' => 'British pound',
                    'de' => 'Britisches Pfund',
                    'fr' => 'Livre sterling',
                    'es' => 'Libra esterlina',
                ],
            ],
            [
                'code' => 'CHF',
                'sort_order' => 4,
                'translations' => [
                    'nl' => 'Zwitserse frank',
                    'en' => 'Swiss franc',
                    'de' => 'Schweizer Franken',
                    'fr' => 'Franc suisse',
                    'es' => 'Franco suizo',
                ],
            ],
            [
                'code' => 'NLG',
                'sort_order' => 5,
                'translations' => [
                    'nl' => 'Nederlandse gulden',
                    'en' => 'Dutch guilder',
                    'de' => 'Niederländischer Gulden',
                    'fr' => 'Florin néerlandais',
                    'es' => 'Florín neerlandés',
                ],
            ],
            [
                'code' => 'DEM',
                'sort_order' => 6,
                'translations' => [
                    'nl' => 'Duitse mark',
                    'en' => 'German mark',
                    'de' => 'Deutsche Mark',
                    'fr' => 'Mark allemand',
                    'es' => 'Marco alemán',
                ],
            ],
            [
                'code' => 'BEF',
                'sort_order' => 7,
                'translations' => [
                    'nl' => 'Belgische frank',
                    'en' => 'Belgian franc',
                    'de' => 'Belgischer Franken',
                    'fr' => 'Franc belge',
                    'es' => 'Franco belga',
                ],
            ],
            [
                'code' => 'FRF',
                'sort_order' => 8,
                'translations' => [
                    'nl' => 'Franse frank',
                    'en' => 'French franc',
                    'de' => 'Französischer Franc',
                    'fr' => 'Franc français',
                    'es' => 'Franco francés',
                ],
            ],
            [
                'code' => 'ITL',
                'sort_order' => 9,
                'translations' => [
                    'nl' => 'Italiaanse lire',
                    'en' => 'Italian lira',
                    'de' => 'Italienische Lira',
                    'fr' => 'Lire italienne',
                    'es' => 'Lira italiana',
                ],
            ],
            [
                'code' => 'ESP',
                'sort_order' => 10,
                'translations' => [
                    'nl' => 'Spaanse peseta',
                    'en' => 'Spanish peseta',
                    'de' => 'Spanische Peseta',
                    'fr' => 'Peseta espagnole',
                    'es' => 'Peseta española',
                ],
            ],
        ];

        foreach ($currencies as $data) {
            $currency = Currency::updateOrCreate(
                ['code' => $data['code']],
                [
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($data['translations'] as $locale => $name) {
                $currency->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name]
                );
            }
        }
    }
}
