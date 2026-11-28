<?php

use App\Models\Stamp;
use App\Models\StampChangeBatch;
use App\Models\StampChangeLog;
use App\Models\User;

test('een succesvolle bulkbewerking slaat wijzigingen en geschiedenis op en ruimt de sessie op', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamps = Stamp::factory()->count(2)->create([
        'designer' => 'Oorspronkelijke ontwerper',
    ]);

    $stampIds = $stamps->modelKeys();

    $selectionKey = "stamp_bulk_selection.{$admin->id}";
    $operationsKey = "stamp_bulk_operations.{$admin->id}";
    $excludedKey = "stamp_bulk_excluded.{$admin->id}";

    $operations = [
        'country_operation' => 'unchanged',
        'issue_year_operation' => 'unchanged',
        'issue_date_operation' => 'unchanged',
        'stamp_series_operation' => 'unchanged',
        'face_value_operation' => 'unchanged',
        'currency_operation' => 'unchanged',
        'designer_operation' => 'set',
        'designer' => 'Nieuwe ontwerper',
        'printing_method_operation' => 'unchanged',
        'notes_operation' => 'unchanged',
        'theme_operation' => 'unchanged',
    ];

    $this->actingAs($admin)
        ->withSession([
            $selectionKey => $stampIds,
            $operationsKey => $operations,
            $excludedKey => [],
        ]);

    $previewResponse = $this->get(
        route('backend.stamps.bulk-edit.preview.show')
    );

    $previewResponse->assertOk();

    $previewHash = $previewResponse->viewData('previewHash');

    $this->post(
        route('backend.stamps.bulk-edit.apply'),
        [
            'confirmed' => '1',
            'preview_hash' => $previewHash,
        ]
    )
        ->assertRedirect(
            route('backend.stamps.bulk-edit.index')
        )
        ->assertSessionHas('success')
        ->assertSessionMissing($selectionKey)
        ->assertSessionMissing($operationsKey)
        ->assertSessionMissing($excludedKey);

    foreach ($stamps as $stamp) {
        expect($stamp->fresh()->designer)
            ->toBe('Nieuwe ontwerper');
    }

    expect(StampChangeBatch::count())->toBe(1)
        ->and(StampChangeLog::count())->toBe(2);

    $batch = StampChangeBatch::query()->sole();

    expect($batch->user_id)->toBe($admin->id)
        ->and($batch->user_name)->toBe($admin->name)
        ->and($batch->operation)->toBe('bulk_edit')
        ->and($batch->stamp_count)->toBe(2)
        ->and($batch->operations)->toBe($operations);

    foreach ($stamps as $stamp) {
        $log = $batch->logs()
            ->where('stamp_id', $stamp->id)
            ->sole();

        expect($log->stamp_name)->toBe($stamp->name)
            ->and($log->old_values)->toBe([
                'designer' => 'Oorspronkelijke ontwerper',
            ])
            ->and($log->new_values)->toBe([
                'designer' => 'Nieuwe ontwerper',
            ]);
    }
});

test('uitgesloten en ongewijzigde postzegels worden niet aangepast of geregistreerd', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $changedStamp = Stamp::factory()->create([
        'designer' => 'Oorspronkelijke ontwerper',
    ]);

    $unchangedStamp = Stamp::factory()->create([
        'designer' => 'Nieuwe ontwerper',
    ]);

    $excludedStamp = Stamp::factory()->create([
        'designer' => 'Uitgesloten ontwerper',
    ]);

    $operations = [
        'country_operation' => 'unchanged',
        'issue_year_operation' => 'unchanged',
        'issue_date_operation' => 'unchanged',
        'stamp_series_operation' => 'unchanged',
        'face_value_operation' => 'unchanged',
        'currency_operation' => 'unchanged',
        'designer_operation' => 'set',
        'designer' => 'Nieuwe ontwerper',
        'printing_method_operation' => 'unchanged',
        'notes_operation' => 'unchanged',
        'theme_operation' => 'unchanged',
    ];

    $this->actingAs($admin)
        ->withSession([
            "stamp_bulk_selection.{$admin->id}" => [
                $changedStamp->id,
                $unchangedStamp->id,
                $excludedStamp->id,
            ],
            "stamp_bulk_operations.{$admin->id}" => $operations,
            "stamp_bulk_excluded.{$admin->id}" => [
                $excludedStamp->id,
            ],
        ]);

    $previewResponse = $this->get(
        route('backend.stamps.bulk-edit.preview.show')
    );

    $previewResponse->assertOk();

    expect($previewResponse->viewData('summary'))->toBe([
        'total' => 3,
        'changed' => 1,
        'unchanged' => 1,
        'conflict' => 0,
        'excluded' => 1,
    ]);

    $this->post(
        route('backend.stamps.bulk-edit.apply'),
        [
            'confirmed' => '1',
            'preview_hash' => $previewResponse->viewData('previewHash'),
        ]
    )
        ->assertRedirect(
            route('backend.stamps.bulk-edit.index')
        )
        ->assertSessionHas('success');

    expect($changedStamp->fresh()->designer)
        ->toBe('Nieuwe ontwerper')
        ->and($unchangedStamp->fresh()->designer)
        ->toBe('Nieuwe ontwerper')
        ->and($excludedStamp->fresh()->designer)
        ->toBe('Uitgesloten ontwerper');

    expect(StampChangeBatch::count())->toBe(1)
        ->and(StampChangeLog::count())->toBe(1);

    $batch = StampChangeBatch::query()->sole();
    $log = StampChangeLog::query()->sole();

    expect($batch->stamp_count)->toBe(1)
        ->and($log->stamp_id)->toBe($changedStamp->id)
        ->and($log->old_values)->toBe([
            'designer' => 'Oorspronkelijke ontwerper',
        ])
        ->and($log->new_values)->toBe([
            'designer' => 'Nieuwe ontwerper',
        ]);
});

test('een verouderd wijzigingsoverzicht wordt geweigerd zonder wijzigingen op te slaan', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamps = Stamp::factory()->count(2)->create([
        'designer' => 'Oorspronkelijke ontwerper',
    ]);

    $operations = [
        'country_operation' => 'unchanged',
        'issue_year_operation' => 'unchanged',
        'issue_date_operation' => 'unchanged',
        'stamp_series_operation' => 'unchanged',
        'face_value_operation' => 'unchanged',
        'currency_operation' => 'unchanged',
        'designer_operation' => 'set',
        'designer' => 'Nieuwe ontwerper',
        'printing_method_operation' => 'unchanged',
        'notes_operation' => 'unchanged',
        'theme_operation' => 'unchanged',
    ];

    $selectionKey = "stamp_bulk_selection.{$admin->id}";
    $operationsKey = "stamp_bulk_operations.{$admin->id}";
    $excludedKey = "stamp_bulk_excluded.{$admin->id}";

    $this->actingAs($admin)
        ->withSession([
            $selectionKey => $stamps->modelKeys(),
            $operationsKey => $operations,
            $excludedKey => [],
        ]);

    $previewResponse = $this->get(
        route('backend.stamps.bulk-edit.preview.show')
    );

    $previewResponse->assertOk();

    $previewHash = $previewResponse->viewData('previewHash');

    // Simuleer een tussentijdse wijziging.
    $stamps[0]->update([
        'designer' => 'Tussentijds gewijzigde ontwerper',
    ]);

    $this->post(
        route('backend.stamps.bulk-edit.apply'),
        [
            'confirmed' => '1',
            'preview_hash' => $previewHash,
        ]
    )
        ->assertRedirect(
            route('backend.stamps.bulk-edit.preview.show')
        )
        ->assertSessionHas('error')
        ->assertSessionMissing('success')
        ->assertSessionHas($selectionKey, $stamps->modelKeys())
        ->assertSessionHas($operationsKey, $operations)
        ->assertSessionHas($excludedKey, []);

    expect($stamps[0]->fresh()->designer)
        ->toBe('Tussentijds gewijzigde ontwerper')
        ->and($stamps[1]->fresh()->designer)
        ->toBe('Oorspronkelijke ontwerper');

    expect(StampChangeBatch::count())->toBe(0)
        ->and(StampChangeLog::count())->toBe(0);
});

test('een opslagfout draait alle postzegelwijzigingen en geschiedenisregistraties terug', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamps = Stamp::factory()->count(2)->create([
        'designer' => 'Oorspronkelijke ontwerper',
    ]);

    $operations = [
        'country_operation' => 'unchanged',
        'issue_year_operation' => 'unchanged',
        'issue_date_operation' => 'unchanged',
        'stamp_series_operation' => 'unchanged',
        'face_value_operation' => 'unchanged',
        'currency_operation' => 'unchanged',
        'designer_operation' => 'set',
        'designer' => 'Nieuwe ontwerper',
        'printing_method_operation' => 'unchanged',
        'notes_operation' => 'unchanged',
        'theme_operation' => 'unchanged',
    ];

    $selectionKey = "stamp_bulk_selection.{$admin->id}";
    $operationsKey = "stamp_bulk_operations.{$admin->id}";
    $excludedKey = "stamp_bulk_excluded.{$admin->id}";

    $this->actingAs($admin)
        ->withSession([
            $selectionKey => $stamps->modelKeys(),
            $operationsKey => $operations,
            $excludedKey => [],
        ]);

    $previewResponse = $this->get(
        route('backend.stamps.bulk-edit.preview.show')
    );

    $previewResponse->assertOk();

    $previewHash = $previewResponse->viewData('previewHash');

    $originalDispatcher = StampChangeLog::getEventDispatcher();

    StampChangeLog::setEventDispatcher(
        clone $originalDispatcher
    );

    $attempts = 0;

    StampChangeLog::creating(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 2) {
            throw new RuntimeException(
                'Gesimuleerde opslagfout tijdens bulkbewerking.'
            );
        }
    });

    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->post(
            route('backend.stamps.bulk-edit.apply'),
            [
                'confirmed' => '1',
                'preview_hash' => $previewHash,
            ]
        ))->toThrow(
            RuntimeException::class,
            'Gesimuleerde opslagfout tijdens bulkbewerking.'
        );
    } finally {
        StampChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();
    }

    expect($attempts)->toBe(2);

    foreach ($stamps as $stamp) {
        expect($stamp->fresh()->designer)
            ->toBe('Oorspronkelijke ontwerper');
    }

    expect(StampChangeBatch::count())->toBe(0)
        ->and(StampChangeLog::count())->toBe(0);

    expect(session()->get($selectionKey))
        ->toBe($stamps->modelKeys())
        ->and(session()->get($operationsKey))
        ->toBe($operations)
        ->and(session()->get($excludedKey))
        ->toBe([]);
});

test('een gewone gebruiker mag geen bulkbewerking uitvoeren', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $stamp = Stamp::factory()->create([
        'designer' => 'Oorspronkelijke ontwerper',
    ]);

    $operations = [
        'country_operation' => 'unchanged',
        'issue_year_operation' => 'unchanged',
        'issue_date_operation' => 'unchanged',
        'stamp_series_operation' => 'unchanged',
        'face_value_operation' => 'unchanged',
        'currency_operation' => 'unchanged',
        'designer_operation' => 'set',
        'designer' => 'Nieuwe ontwerper',
        'printing_method_operation' => 'unchanged',
        'notes_operation' => 'unchanged',
        'theme_operation' => 'unchanged',
    ];

    $this->actingAs($user)
        ->withSession([
            "stamp_bulk_selection.{$user->id}" => [
                $stamp->id,
            ],
            "stamp_bulk_operations.{$user->id}" => $operations,
            "stamp_bulk_excluded.{$user->id}" => [],
        ])
        ->post(
            route('backend.stamps.bulk-edit.apply'),
            [
                'confirmed' => '1',
                'preview_hash' => str_repeat('a', 64),
            ]
        )
        ->assertForbidden();

    expect($stamp->fresh()->designer)
        ->toBe('Oorspronkelijke ontwerper');

    expect(StampChangeBatch::count())->toBe(0)
        ->and(StampChangeLog::count())->toBe(0);
});
