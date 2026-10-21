<?php

namespace Database\Seeders;

use App\Models\CollectibleType;
use Illuminate\Database\Seeder;

class CollectibleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'slug' => 'fdc',
                'sort_order' => 1,
                'translations' => [
                    'nl' => 'Eerstedagenvelop',
                    'en' => 'First Day Cover',
                    'de' => 'Ersttagsbrief',
                    'fr' => 'Enveloppe premier jour',
                    'es' => 'Sobre de primer día',
                ],
            ],
            [
                'slug' => 'booklet',
                'sort_order' => 2,
                'translations' => [
                    'nl' => 'Postzegelboekje',
                    'en' => 'Stamp Booklet',
                    'de' => 'Markenheftchen',
                    'fr' => 'Carnet de timbres',
                    'es' => 'Carné de sellos',
                ],
            ],
            [
                'slug' => 'souvenir_sheet',
                'sort_order' => 3,
                'translations' => [
                    'nl' => 'Souvenirvel',
                    'en' => 'Souvenir Sheet',
                    'de' => 'Souvenirblock',
                    'fr' => 'Bloc souvenir',
                    'es' => 'Hoja recuerdo',
                ],
            ],
            [
                'slug' => 'postal_stationery',
                'sort_order' => 4,
                'translations' => [
                    'nl' => 'Postwaardestuk',
                    'en' => 'Postal Stationery',
                    'de' => 'Ganzsache',
                    'fr' => 'Entier postal',
                    'es' => 'Entero postal',
                ],
            ],
            [
                'slug' => 'other',
                'sort_order' => 5,
                'translations' => [
                    'nl' => 'Overig verzamelobject',
                    'en' => 'Other Collectible',
                    'de' => 'Sonstiges Sammelobjekt',
                    'fr' => 'Autre objet de collection',
                    'es' => 'Otro objeto de colección',
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $type = CollectibleType::updateOrCreate(
                ['slug' => $typeData['slug']],
                [
                    'sort_order' => $typeData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($typeData['translations'] as $locale => $name) {
                $type->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name]
                );
            }
        }
    }
}
