<?php

namespace App\Imports\Exchange;

class CatalogEditionExchangeSchema
{
    public const HEADERS = [
        'Metadata' => [
            'key',
            'value',
        ],

        'Catalogus' => [
            'name',
            'abbreviation',
            'publisher',
            'website',
            'description',
        ],

        'Editie' => [
            'edition_name',
            'edition_year',
            'publication_date',
            'currency',
            'notes',
        ],

        'Landen' => [
            'name',
            'iso_code',
            'entity_type',
            'start_year',
            'end_year',
            'is_active',
            'slug',
            'description',
        ],

        'Landvertalingen' => [
            'country_slug',
            'country_iso_code',
            'locale',
            'name',
            'description',
        ],

        'Waarderingsregels' => [
            'country_slug',
            'country_iso_code',
            'condition_slug',
            'issue_year_from',
            'issue_year_to',
            'sort_order',
        ],

        'Postzegelseries' => [
            'exchange_series_id',
            'country_slug',
            'country_iso_code',
            'name',
            'issue_year',
            'description',
        ],

        'Serievertalingen' => [
            'exchange_series_id',
            'locale',
            'name',
            'description',
        ],

        'Postzegels' => [
            'exchange_stamp_id',
            'parent_exchange_stamp_id',
            'catalog_number',
            'exchange_series_id',
            'country_slug',
            'country_iso_code',
            'name',
            'description',
            'issue_year',
            'issue_date',
            'face_value',
            'currency',
            'designer',
            'printing_method',
            'catalog_reference',
            'notes',
            'catalog_entry_notes',
        ],

        'BovenliggendePostzegels' => [
            'parent_exchange_id',
            'child_exchange_stamp_id',
            'country_slug',
            'country_iso_code',
            'name',
            'description',
            'issue_year',
            'issue_date',
            'face_value',
            'currency',
            'designer',
            'printing_method',
            'catalog_reference',
            'notes',
        ],

        'Postzegelvertalingen' => [
            'exchange_stamp_id',
            'catalog_number',
            'locale',
            'name',
            'description',
        ],

        'Cataloguswaarden' => [
            'exchange_stamp_id',
            'catalog_number',
            'condition_slug',
            'catalog_value',
            'currency',
            'notes',
            'needs_review',
        ],

        'Themas' => [
            'theme_slug',
            'name',
            'description',
        ],

        'Themavertalingen' => [
            'theme_slug',
            'locale',
            'name',
            'description',
        ],

        'Postzegelthemas' => [
            'exchange_stamp_id',
            'theme_slug',
        ],

        'Waarderingscategorieen' => [
            'condition_slug',
            'sort_order',
            'is_active',
        ],

        'Waarderingsvertalingen' => [
            'condition_slug',
            'locale',
            'name',
            'description',
        ],

        'Seriecataloguswaarden' => [
            'exchange_series_id',
            'condition_slug',
            'catalog_number',
            'catalog_value',
            'currency',
            'notes',
        ],
    ];
}
