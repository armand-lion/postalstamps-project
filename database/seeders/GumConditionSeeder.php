<?php

namespace Database\Seeders;

use App\Models\GumCondition;
use Illuminate\Database\Seeder;

class GumConditionSeeder extends Seeder
{
    public function run(): void
    {
        $gumConditions = [
            [
                'slug' => 'original_gum',
                'sort_order' => 1,
                'translations' => [
                    'nl' => 'Originele gom',
                    'en' => 'Original gum',
                    'de' => 'Originalgummierung',
                    'fr' => 'Gomme originale',
                    'es' => 'Goma original',
                ],
            ],
            [
                'slug' => 'hinge_remnant',
                'sort_order' => 2,
                'translations' => [
                    'nl' => 'Gom met plakkerrest',
                    'en' => 'Gum with hinge remnant',
                    'de' => 'Gummierung mit Falzrest',
                    'fr' => 'Gomme avec trace de charnière',
                    'es' => 'Goma con resto de charnela',
                ],
            ],
            [
                'slug' => 'damaged_gum',
                'sort_order' => 3,
                'translations' => [
                    'nl' => 'Beschadigde gom',
                    'en' => 'Damaged gum',
                    'de' => 'Beschädigte Gummierung',
                    'fr' => 'Gomme endommagée',
                    'es' => 'Goma dañada',
                ],
            ],
            [
                'slug' => 'no_gum',
                'sort_order' => 4,
                'translations' => [
                    'nl' => 'Zonder gom',
                    'en' => 'Without gum',
                    'de' => 'Ohne Gummierung',
                    'fr' => 'Sans gomme',
                    'es' => 'Sin goma',
                ],
            ],
            [
                'slug' => 'self_adhesive',
                'sort_order' => 5,
                'translations' => [
                    'nl' => 'Zelfklevend',
                    'en' => 'Self-adhesive',
                    'de' => 'Selbstklebend',
                    'fr' => 'Autoadhésif',
                    'es' => 'Autoadhesivo',
                ],
            ],
            [
                'slug' => 'not_applicable',
                'sort_order' => 6,
                'translations' => [
                    'nl' => 'Niet van toepassing',
                    'en' => 'Not applicable',
                    'de' => 'Nicht zutreffend',
                    'fr' => 'Sans objet',
                    'es' => 'No aplicable',
                ],
            ],
        ];

        foreach ($gumConditions as $data) {
            $gumCondition = GumCondition::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($data['translations'] as $locale => $name) {
                $gumCondition->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name]
                );
            }
        }
    }
}
