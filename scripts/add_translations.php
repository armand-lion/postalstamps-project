<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

$languages = ['nl', 'en', 'de', 'fr', 'es'];

/*
|--------------------------------------------------------------------------
| Nieuwe vertalingen
|--------------------------------------------------------------------------
|
| Vul hier de Nederlandse vertaalkey in.
| Geef vervolgens de vertaling per taal op.
|
*/

$translations = require __DIR__.'/translations_fase14f_batch4.php';

/*
|--------------------------------------------------------------------------
| Vertalingen verwerken
|--------------------------------------------------------------------------
*/

foreach ($languages as $language) {
    $file = "{$basePath}/lang/{$language}.json";

    if (! is_file($file)) {
        echo "Bestand niet gevonden: {$file}\n";

        continue;
    }

    $contents = file_get_contents($file);

    if ($contents === false) {
        throw new RuntimeException("Kan bestand niet lezen: {$file}");
    }

    $data = json_decode(
        $contents,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (! is_array($data)) {
        throw new RuntimeException("Ongeldige vertaalstructuur: {$file}");
    }

    $added = 0;

    foreach ($translations as $key => $values) {
        if (! array_key_exists($language, $values)) {
            continue;
        }

        if (! array_key_exists($key, $data)) {
            $data[$key] = $values[$language];
            $added++;
        }
    }

    ksort($data, SORT_NATURAL | SORT_FLAG_CASE);

    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($file, $json.PHP_EOL) === false) {
        throw new RuntimeException("Kan bestand niet opslaan: {$file}");
    }

    echo strtoupper($language)
        .": {$added} vertalingen toegevoegd en alfabetisch gesorteerd.\n";
}

echo "\nAlle taalbestanden zijn verwerkt.\n";
