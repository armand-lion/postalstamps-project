<?php

namespace Database\Seeders;

use App\Models\Condition;
use App\Models\ConditionTranslation;
use Illuminate\Database\Seeder;

class ConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            [
                'slug' => 'postfris',
                'sort_order' => 1,
                'translations' => [
                    'nl' => ['name' => 'Postfris', 'description' => 'Ongebruikt, met originele gom en zonder plakker.'],
                    'en' => ['name' => 'Mint never hinged', 'description' => 'Unused, with original gum and without a hinge.'],
                    'de' => ['name' => 'Postfrisch', 'description' => 'Ungebraucht, mit Originalgummi und ohne Falz.'],
                    'fr' => ['name' => 'Neuf sans charnière', 'description' => 'Timbre neuf, avec gomme d’origine et sans charnière.'],
                    'es' => ['name' => 'Nuevo sin charnela', 'description' => 'Sin usar, con goma original y sin charnela.'],
                ],
            ],
            [
                'slug' => 'ongebruikt-met-plakker',
                'sort_order' => 2,
                'translations' => [
                    'nl' => ['name' => 'Ongebruikt met plakker', 'description' => 'Ongebruikt exemplaar met plakker of plakkerrest.'],
                    'en' => ['name' => 'Unused with hinge', 'description' => 'Unused stamp with a hinge or hinge remnant.'],
                    'de' => ['name' => 'Ungebraucht mit Falz', 'description' => 'Ungebrauchte Marke mit Falz oder Falzrest.'],
                    'fr' => ['name' => 'Neuf avec charnière', 'description' => 'Timbre neuf avec charnière ou trace de charnière.'],
                    'es' => ['name' => 'Nuevo con charnela', 'description' => 'Sello sin usar con charnela o restos de charnela.'],
                ],
            ],
            [
                'slug' => 'ongebruikt-zonder-gom',
                'sort_order' => 3,
                'translations' => [
                    'nl' => ['name' => 'Ongebruikt zonder gom', 'description' => 'Ongebruikt exemplaar zonder originele gom.'],
                    'en' => ['name' => 'Unused without gum', 'description' => 'Unused stamp without its original gum.'],
                    'de' => ['name' => 'Ungebraucht ohne Gummi', 'description' => 'Ungebrauchte Marke ohne Originalgummi.'],
                    'fr' => ['name' => 'Neuf sans gomme', 'description' => 'Timbre neuf sans gomme d’origine.'],
                    'es' => ['name' => 'Nuevo sin goma', 'description' => 'Sello sin usar y sin goma original.'],
                ],
            ],
            [
                'slug' => 'gestempeld',
                'sort_order' => 4,
                'translations' => [
                    'nl' => ['name' => 'Gestempeld', 'description' => 'Gebruikt exemplaar met een stempel.'],
                    'en' => ['name' => 'Used', 'description' => 'Used stamp bearing a postmark.'],
                    'de' => ['name' => 'Gestempelt', 'description' => 'Gebrauchte Marke mit Stempel.'],
                    'fr' => ['name' => 'Oblitéré', 'description' => 'Timbre utilisé portant une oblitération.'],
                    'es' => ['name' => 'Usado', 'description' => 'Sello usado con matasellos.'],
                ],
            ],
            [
                'slug' => 'licht-gestempeld',
                'sort_order' => 5,
                'translations' => [
                    'nl' => ['name' => 'Licht gestempeld', 'description' => 'Gebruikt exemplaar met een lichte stempel.'],
                    'en' => ['name' => 'Lightly cancelled', 'description' => 'Used stamp with a light postmark.'],
                    'de' => ['name' => 'Leicht gestempelt', 'description' => 'Gebrauchte Marke mit leichtem Stempelabdruck.'],
                    'fr' => ['name' => 'Légèrement oblitéré', 'description' => 'Timbre utilisé avec une oblitération légère.'],
                    'es' => ['name' => 'Ligeramente matasellado', 'description' => 'Sello usado con un matasellos ligero.'],
                ],
            ],
            [
                'slug' => 'beschadigd',
                'sort_order' => 6,
                'translations' => [
                    'nl' => ['name' => 'Beschadigd', 'description' => 'Exemplaar met schade, zoals scheuren of ontbrekende tandjes.'],
                    'en' => ['name' => 'Damaged', 'description' => 'Stamp with damage, such as tears or missing perforations.'],
                    'de' => ['name' => 'Beschädigt', 'description' => 'Marke mit Schäden, etwa Rissen oder fehlenden Zähnen.'],
                    'fr' => ['name' => 'Endommagé', 'description' => 'Timbre présentant des dommages, comme des déchirures ou des dents manquantes.'],
                    'es' => ['name' => 'Dañado', 'description' => 'Sello con daños, como roturas o dientes faltantes.'],
                ],
            ],
        ];

        foreach ($conditions as $conditionData) {
            $condition = Condition::updateOrCreate(
                ['slug' => $conditionData['slug']],
                [
                    'sort_order' => $conditionData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($conditionData['translations'] as $locale => $translationData) {
                ConditionTranslation::updateOrCreate(
                    [
                        'condition_id' => $condition->id,
                        'locale' => $locale,
                    ],
                    [
                        'name' => $translationData['name'],
                        'description' => $translationData['description'],
                    ]
                );
            }
        }
    }
}
