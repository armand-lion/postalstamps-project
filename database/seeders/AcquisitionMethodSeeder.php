<?php

namespace Database\Seeders;

use App\Models\AcquisitionMethod;
use Illuminate\Database\Seeder;

class AcquisitionMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'slug' => 'gekocht',
                'sort_order' => 10,
                'translations' => [
                    'nl' => 'Gekocht',
                    'en' => 'Purchased',
                    'de' => 'Gekauft',
                    'fr' => 'Acheté',
                    'es' => 'Comprado',
                ],
            ],
            [
                'slug' => 'geruild',
                'sort_order' => 20,
                'translations' => [
                    'nl' => 'Geruild',
                    'en' => 'Traded',
                    'de' => 'Getauscht',
                    'fr' => 'Échangé',
                    'es' => 'Intercambiado',
                ],
            ],
            [
                'slug' => 'gekregen',
                'sort_order' => 30,
                'translations' => [
                    'nl' => 'Gekregen',
                    'en' => 'Received as a gift',
                    'de' => 'Geschenkt bekommen',
                    'fr' => 'Reçu en cadeau',
                    'es' => 'Recibido como regalo',
                ],
            ],
            [
                'slug' => 'geerfd',
                'sort_order' => 40,
                'translations' => [
                    'nl' => 'Geërfd',
                    'en' => 'Inherited',
                    'de' => 'Geerbt',
                    'fr' => 'Hérité',
                    'es' => 'Heredado',
                ],
            ],
            [
                'slug' => 'gevonden',
                'sort_order' => 50,
                'translations' => [
                    'nl' => 'Gevonden',
                    'en' => 'Found',
                    'de' => 'Gefunden',
                    'fr' => 'Trouvé',
                    'es' => 'Encontrado',
                ],
            ],
            [
                'slug' => 'overig',
                'sort_order' => 60,
                'translations' => [
                    'nl' => 'Overig',
                    'en' => 'Other',
                    'de' => 'Sonstiges',
                    'fr' => 'Autre',
                    'es' => 'Otro',
                ],
            ],
        ];

        foreach ($methods as $methodData) {
            $method = AcquisitionMethod::firstOrCreate(
                ['slug' => $methodData['slug']],
                [
                    'sort_order' => $methodData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($methodData['translations'] as $locale => $name) {
                $method->translations()->firstOrCreate(
                    ['locale' => $locale],
                    ['name' => $name]
                );
            }
        }
    }
}
