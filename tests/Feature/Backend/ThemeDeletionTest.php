<?php

use App\Models\Stamp;
use App\Models\Theme;
use App\Models\User;

test('de backend kan een thema zonder gekoppelde postzegels verwijderen', function () {
    $user = User::factory()->create();

    $theme = Theme::create([
        'name' => 'Testthema verwijderen',
        'slug' => 'testthema-verwijderen',
    ]);

    $theme->translations()->create([
        'locale' => 'nl',
        'name' => 'Testthema verwijderen',
    ]);

    $themeId = $theme->id;

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.themes.destroy', $theme));

    $response->assertRedirect(route('backend.themes.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('themes', [
        'id' => $themeId,
    ]);

    $this->assertDatabaseMissing('theme_translations', [
        'theme_id' => $themeId,
    ]);
});

test('de backend weigert een thema met gekoppelde postzegels te verwijderen', function () {
    $user = User::factory()->create();

    $theme = Theme::create([
        'name' => 'Testthema met postzegel',
        'slug' => 'testthema-met-postzegel',
    ]);

    $theme->translations()->create([
        'locale' => 'nl',
        'name' => 'Testthema met postzegel',
    ]);

    $stamp = Stamp::factory()->create([
        'name' => 'Testpostzegel met thema',
    ]);

    // Koppel de postzegel aan het thema.
    $theme->stamps()->attach($stamp->id);

    // Probeer het gekoppelde thema te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.themes.destroy', $theme));

    // De backend moet de verwijdering weigeren.
    $response->assertRedirect(route('backend.themes.show', $theme));
    $response->assertSessionHas('error');

    // Het thema moet behouden blijven.
    $this->assertDatabaseHas('themes', [
        'id' => $theme->id,
        'name' => 'Testthema met postzegel',
    ]);

    // De gekoppelde postzegel moet behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);

    // De koppeling moet behouden blijven.
    $this->assertDatabaseHas('stamp_themes', [
        'theme_id' => $theme->id,
        'stamp_id' => $stamp->id,
    ]);

    // Ook de vertaling moet behouden blijven.
    $this->assertDatabaseHas('theme_translations', [
        'theme_id' => $theme->id,
        'locale' => 'nl',
        'name' => 'Testthema met postzegel',
    ]);
});
