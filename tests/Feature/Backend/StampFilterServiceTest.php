<?php

use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use App\Models\Theme;
use App\Models\User;
use App\Services\StampBulkSelectionService;
use App\Services\StampFilterService;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->country = Country::factory()->create();
    $this->otherCountry = Country::factory()->create();

    $this->series = StampSeries::create([
        'country_id' => $this->country->id,
        'name' => 'Testserie 2020',
        'issue_year' => 2020,
    ]);

    $this->theme = Theme::create([
        'name' => 'Testthema filters',
        'slug' => 'testthema-filters',
    ]);

    $this->mainStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'stamp_series_id' => $this->series->id,
        'issue_year' => 2020,
        'printing_method' => 'Offset',
        'designer' => 'Testontwerper',
        'parent_stamp_id' => null,
    ]);

    $this->mainStamp->themes()->attach($this->theme->id);

    $this->variantStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'stamp_series_id' => $this->series->id,
        'issue_year' => 2020,
        'printing_method' => 'Diepdruk',
        'designer' => null,
        'parent_stamp_id' => $this->mainStamp->id,
    ]);

    $this->standaloneStamp = Stamp::factory()->create([
        'country_id' => $this->otherCountry->id,
        'stamp_series_id' => null,
        'issue_year' => 2021,
        'printing_method' => 'Offset',
        'designer' => 'Andere ontwerper',
        'parent_stamp_id' => null,
    ]);
});

test('de aanvullende postzegelfilters leveren afzonderlijk de juiste resultaten op', function () {
    $service = app(StampFilterService::class);

    $filter = function (array $parameters) use ($service): array {
        $request = Request::create('/', 'GET', $parameters);

        return $service->applyFilters(Stamp::query(), $request)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    };

    expect($filter([
        'stamp_series_id' => $this->series->id,
    ]))->toBe([
        $this->mainStamp->id,
        $this->variantStamp->id,
    ]);

    expect($filter([
        'theme_id' => $this->theme->id,
    ]))->toBe([
        $this->mainStamp->id,
    ]);

    expect($filter([
        'printing_method' => 'Offset',
    ]))->toBe([
        $this->mainStamp->id,
        $this->standaloneStamp->id,
    ]);

    expect($filter([
        'missing_field' => 'designer',
    ]))->toBe([
        $this->variantStamp->id,
    ]);

    expect($filter([
        'missing_field' => 'themes',
    ]))->toBe([
        $this->variantStamp->id,
        $this->standaloneStamp->id,
    ]);

    expect($filter([
        'stamp_type' => 'main',
    ]))->toBe([
        $this->mainStamp->id,
        $this->standaloneStamp->id,
    ]);

    expect($filter([
        'stamp_type' => 'parent',
    ]))->toBe([
        $this->mainStamp->id,
    ]);

    expect($filter([
        'stamp_type' => 'variant',
    ]))->toBe([
        $this->variantStamp->id,
    ]);

    expect($filter([
        'stamp_type' => 'standalone',
    ]))->toBe([
        $this->standaloneStamp->id,
    ]);
});

test('meerdere postzegelfilters kunnen worden gecombineerd', function () {
    $service = app(StampFilterService::class);

    $request = Request::create('/', 'GET', [
        'country_id' => $this->country->id,
        'issue_year' => 2020,
        'stamp_series_id' => $this->series->id,
        'theme_id' => $this->theme->id,
        'printing_method' => 'Offset',
        'stamp_type' => 'parent',
    ]);

    $result = $service->applyFilters(Stamp::query(), $request)
        ->pluck('id')
        ->all();

    expect($result)->toBe([
        $this->mainStamp->id,
    ]);
});

test('de bulkselectie houdt rekening met de aanvullende postzegelfilters', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $filters = [
        'country_id' => $this->country->id,
        'issue_year' => 2020,
        'stamp_series_id' => $this->series->id,
        'theme_id' => $this->theme->id,
        'printing_method' => 'Offset',
    ];

    $this->actingAs($admin)
        ->post(route(
            'backend.stamps.bulk-edit.selection.all-filtered',
            $filters
        ))
        ->assertRedirect();

    $service = app(StampBulkSelectionService::class);

    expect($service->getSelectedIds($admin->id))
        ->toBe([$this->mainStamp->id]);

    $this->get(route('backend.stamps.bulk-edit.index', $filters))
        ->assertOk()
        ->assertViewHas('selectedCount', 1)
        ->assertViewHas('selectedWithinFiltersCount', 1)
        ->assertViewHas('selectedOutsideFiltersCount', 0);
});
