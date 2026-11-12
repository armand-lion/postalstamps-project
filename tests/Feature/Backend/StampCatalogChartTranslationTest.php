<?php

test('de historische catalogusgrafiek heeft vertalingen in alle vijf talen', function () {
    $locales = ['nl', 'en', 'de', 'fr', 'es'];

    $keys = [
        'Ontwikkeling cataloguswaarden',
        'Grafiek met historische cataloguswaarden',
        'Er zijn onvoldoende vergelijkbare cataloguswaarden beschikbaar om een grafiek te tonen.',
    ];

    foreach ($locales as $locale) {
        $translations = json_decode(
            file_get_contents(lang_path($locale.'.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($keys as $key) {
            expect($translations)
                ->toHaveKey($key);

            expect($translations[$key])
                ->toBeString()
                ->not->toBeEmpty();

            expect(__($key, [], $locale))
                ->toBe($translations[$key]);
        }
    }
});
