# postalstamps-project

This is a project for stamp collections.

We will build the stamp archive step by step using Laravel, MariaDB or MySQL, and Eloquent, with a database design that also supports multiple users.

The scope is extensive:

- Multiple countries, series, and themes.
- Individual items, each with its own condition and specific details.
- Multiple catalogs and editions per country.
- Catalog values ​​and historical value charts.
- Images stored as files, with only the file paths kept in MariaDB.
- Support for personal collections and catalog holdings for multiple users.

The next step is to develop the complete database schema and then implement the Laravel migrations. We will retain Laravel’s existing default migrations and build upon them.

## Fase 1

Let’s get started!

We will build the stamp archive using Laravel and MariaDB in a way that allows for the addition of new functionality later on, without needing to redesign the foundation.
We will work with Laravel migrations and Eloquent models. We’ll keep Laravel’s default tables—such as the `users` table—and add our own tables alongside them.
I suggest building the project in phases. We’ll begin by establishing the database foundation and the relationships between countries, series, stamps, and individual specimens. Following that, we will add catalogs, value history, images, and user collections.

Fase 1 is now completed

## Fase 2

Once this phase is working, we will move on to:

- Catalogs: Michel, NVPH, Yvert & Tellier, and other catalogs.
- Catalog editions: individual annual editions containing historical catalog values.
- Value history: the price trajectory of stamps, both for individual items and complete sets.
- Images: multiple images per stamp and per individual item.
- User collections: allowing each user to manage their own collection and catalog holdings.

Following that, we will create the Eloquent models and define the relationships, enabling Laravel to easily retrieve and store the data.

### Step 1

The next step is the catalogue system. We are designing this from the outset to support multiple users, distinct catalogues for different countries, and historical catalogue values

In doing so, we keep three elements strictly separate:

- Catalogue data: which stamp appears in which catalogue.
- Catalogue editions: the data and values ​​associated with a specific edition.
- Catalogue holdings: which editions a user personally owns.

### Step 2

Next, we are adding value history. In doing so, we make an important distinction between three types of valuation:

1. Stamp value: the general value of a specific stamp, for example, based on a current market assessment.
2. Individual item value: the estimated value of your specific specimen, taking into account its condition, rarity, and any printing errors.
3. Series value: the value of a complete set of stamps.

Catalog values ​​will remain in the existing tables. This will allow us to create charts later on that display catalog values ​​and other types of valuations separately.

### Step 3

Once the migrations have been successfully completed, we build the image structure.
In doing so, we support:

- Multiple images per postage stamp.
- Separate photos of individual specimens.
- Front, back, and detail photos.
- Relative file paths in MariaDB.
- Files organized by country and year of issue.

Fase 2 is completed.

## Fase 3 Eloquent models and relationships

This phase is important: it allows Laravel, for example, to retrieve all stamps from a specific country, display individual copies of a particular stamp, link catalogue values, and look up valuation history.
Each physical copy is assigned its own record, enabling you to register rare variants and printing errors separately.

n make:model SeriesValuation

We now move on to populating the database with basic data using seeders. This saves you from having to enter general information manually.
We will start with three tables:

- Countries: the countries from which you collect stamps.
- Themes: subjects such as animals, sports, art, and architecture.
- Conditions: the condition categories for your stamps.

Fase 3 is completed

## Fase 4

Changes in the database structure to support multiple langues as Dutch, English, German, Frech, and Spanish.

Created the first backend views for the stamps, and stamp-series.

Face 4 completed

## Fase 5

5 Language translations added: Dutch, English, German, French and Spanish

Validation added for these 5 languages.

Some small changes on the stamps views and stamp-series. To keep the validation-messages and field input field aligned.

Fase 5 completed

## Fase 6

Starting with with digital version of the stamp catalog.

I propose implementing the changes in the following order:

1. Verify whether the current migrations have already been executed.
2. Define the final structure for catalog entries and values.
3. Add the relationship between primary stamps and their variants.
4. Design the tables for technical specifications.
5. Create the necessary new migrations.
6. Adjust the existing Eloquent models and relationships.
7. Test everything using a sample catalog, including stamp 379 with variants 379a–d.
8. Set fallback for missing translations
9. Multilingual support in views
10. Multilingual support by create and edit

Fase 6 completed

## Fase 7

Countries

- Test search and filtering functionality.
- Test filtering by type of historical entity and active status (to the extent that these filters have been implemented).
- Verify mandatory fields and validation.
- Check the outcome when attempting to delete a country or historical territory that is still linked to stamps.

Stamps and Stamp Series

- Verify that the relationships between stamps, series, and countries continue to function correctly.
- Ensure that existing data is preserved when modifications are made.
- Check that translations are displayed correctly.

Fase 7 complete

## Fase 8

For the catalogue module, we previously established the following premises:

- A single country may have multiple stamp catalogues.
- Each catalogue can have various editions—for example, from 2024, 2025, and 2026.
- A single stamp can appear in multiple catalogues.
- It must be possible to store catalogue values ​​for each individual edition.
- Ultimately, we aim to display the value trends of stamps and series through charts.

To this end, we have already created several models, including `Catalog`, `CatalogEdition`, `StampCatalogValue`, and `SeriesCatalogValue`.

Fase 8 completed

## Fase 9

Implementing different Catalogs editions.

Meaning that 1 catalogus can have more edition, like 2024, 2025, 2026.
Each edition can have different stamps catalog-values. These are placed in different datatables.
When stamps are connected with an catalog-edition, that edition can not be deleted, validations are making sure of that.
All translations in 5 languages are modified.

Fase 9 completed

## Phase 10 – Special Collectibles and Digital Catalog Structure

Phase 10 introduces support for special philatelic collectibles alongside
individual postage stamps.

The application now provides a database structure and backend functionality
for managing special collectibles, such as First Day Covers (FDCs).

### Database Structure

The following database tables have been introduced:

- `collectible_types` – Categories of special collectibles.
- `collectible_type_translations` – Multilingual collectible type names.
- `collectibles` – General information about special collectibles.
- `collectible_stamps` – Many-to-many relationships between collectibles and stamps.
- `collectible_images` – Images associated with collectibles.
- `collectible_catalog_entries` – Catalog registrations for collectibles.
- `collectible_catalog_values` – Catalog values for collectibles.
- `collectible_copies` – Individual collectible copies in a personal collection.

### Laravel Models and Relationships

New Eloquent models have been implemented for the collectible database structure.

Relationships have also been added to existing models, including:

- `Stamp`
- `Country`
- `StampSeries`
- `Condition`
- `CatalogEdition`

These relationships integrate special collectibles with the existing
stamp collection and catalog structure.

### Backend Functionality

The backend now supports:

- Creating special collectibles.
- Viewing collectible details.
- Editing existing collectibles.
- Deleting collectibles with confirmation.
- Assigning collectible types and countries.
- Linking collectibles to existing stamp series.
- Linking multiple postage stamps to a collectible.
- Filtering available stamps by country.
- Updating and removing stamp relationships.

Deleting a collectible removes its stamp relationships without deleting
the associated postage stamps.

Collectibles with dependent images, catalog entries, or personal copies
are protected against deletion.

### Multilingual Support

The application supports five languages:

- Dutch
- English
- German
- French
- Spanish

Translation files have been updated for the new collectible functionality.

### Code Quality and Testing

The following checks have been completed:

- PHP syntax validation.
- Laravel automated tests: 34 passed, 83 assertions.
- Laravel Pint: 132 files passed.
- JSON translation validation.
- Manual testing of collectible creation, editing, stamp linking, and deletion.

### Status

The basic management functionality and database foundation for special
collectibles have been implemented and tested.

The database structure also prepares the application for further
development of collectible images, catalog registrations, catalog values,
and personal collection copies.

Fase 10 completed

## Fase 11 - Stamp Management

The following features will be developed during this phase:

Stamp Management: Create, view, edit, and delete stamps.

Catalog Overview: Search, filter, sort, and paginate stamp records.

Stamp Details: Manage countries, issue years, face values, currencies, serial numbers, and other stamp attributes.

Multilingual Support: Manage descriptions and other translatable content in five languages.

Image Management: Upload, replace, and delete stamp images.

Stamp Series: Associate stamps with existing stamp series.

Catalog Integration: Link stamps to catalogs and catalog editions established in Phase 10.

Data Validation: Validate user input and prevent duplicate catalog entries where applicable.

User Interface: Maintain the existing brown-themed backend design.

Quality Assurance: Use Laravel Pint, PHPStan, Laravel tests, and GitHub Actions to maintain code quality and reliability.

Existing models, relationships, and database fields will be reused wherever possible to avoid duplicating functionality implemented in previous phases.

Fase 11 complete

## Phase 12 — Personal Stamp Collection Management

### Objective

Develop a management system for personal stamp collections.

The existing stamp catalog serves as the foundation. Users can indicate which stamps they own and manage their individual copies.

### Planned Tasks

- [x] Implement backend CRUD operations for stamp copies (StampCopy).
- [x] Link stamp copies to existing stamps (Stamp).
- [x] Register the condition of each stamp copy.
- [x] Track quantities for each condition.
- [x] Manage personal images for individual stamp copies.
- [x] Develop an overview of the personal stamp collection.
- [x] Implement filtering by country, year, stamp, and condition.
- [x] Indicate which stamps are owned in the catalog.
- [x] Display missing stamps with reduced opacity.
- [x] Allow stamps to be added directly to the personal collection from the catalog.
- [x] Support multilingual functionality (Dutch, English, German, French, and Spanish).
- [x] Maintain the existing brown-themed design and Flux UI components.
- [x] Implement validation and error handling.
- [x] Add feature tests for stamp collection management.
- [x] Run Laravel Pint, PHPStan, and the complete test suite.
- [x] Commit changes to Git and push them to GitHub.

### Acceptance Criteria

Phase 12 is complete when:

1. Stamp copies can be fully managed through the backend.
2. The condition and quantity of each stamp can be recorded.
3. Personal images can be associated with individual stamp copies.
4. Owned and missing stamps are clearly distinguishable.
5. All functionality works in all five supported languages.
6. All automated quality checks pass successfully.

| --- | --- | — personal stamp collection management implemented, tested, and published.

## Fase 13

### Part A — Watermark Management

**Status:** ✅ Completed — implemented and functionally tested.

Objective

Implement a complete watermark management system to support the identification and classification of postage stamps and their variants.

Watermarks can be associated with countries and include images, alternative text, and multilingual descriptions.

### Implemented Features

| Feature              | Description                                             | Status |
| -------------------- | ------------------------------------------------------- | ------ |
| Watermark overview   | Display registered watermarks                           | ✅     |
| Country association  | Associate watermarks with countries                     | ✅     |
| Create               | Add new watermarks                                      | ✅     |
| View                 | Display watermark details                               | ✅     |
| Edit                 | Update existing watermarks                              | ✅     |
| Delete               | Remove watermarks                                       | ✅     |
| Image upload         | Upload JPG, PNG, and WebP images                        | ✅     |
| Image replacement    | Replace existing watermark images                       | ✅     |
| Image removal        | Remove an existing image without deleting the watermark | ✅     |
| Image preview        | Preview selected images before uploading                | ✅     |
| Alternative text     | Store and update descriptive image text                 | ✅     |
| File management      | Remove unused image files from storage                  | ✅     |
| Multilingual support | Dutch, English, German, French, and Spanish             | ✅     |
| Sidebar navigation   | Watermarks integrated into Catalog Management           | ✅     |

#### Technical Implementation

The watermark management system is built using:

- Laravel 13
- PHP 8.5
- MariaDB
- Blade templates
- Flux UI
- Alpine.js
- Tailwind CSS

**Backend components:**

- `WatermarkController` — Handles watermark CRUD operations.
- `Watermark` — Main watermark model.
- `WatermarkTranslation` — Stores multilingual watermark information.
- `WatermarkImage` — Manages watermark image records.
- Database migrations for watermarks, translations, and images.
- Resource routes under `backend.watermarks.*`.

**Frontend components:**

- Watermark overview page.
- Watermark creation form.
- Watermark detail page.
- Watermark editing form.
- Multilingual image upload controls.
- Image preview and removal functionality.

#### Image Storage

Watermark images are stored using Laravel's public storage disk.

Directory structure:

```text
storage/app/public/watermarks/{country-slug}/
```

Only the relative image path is stored in the database.

When an image is replaced or removed, the corresponding database record and physical file are updated or deleted.

Removing an image does not delete its associated watermark.

#### User Interface

The watermark management interface follows the application's existing brown color scheme and uses consistent Flux UI components.

The image upload interface supports:

- Multilingual file selection controls.
- Display of the selected filename.
- Image previews before uploading.
- Removal of a newly selected image.
- Removal of an existing image from the edit page.
- Keyboard-accessible file selection.

#### Testing and Validation

The following checks have been completed:

- Watermark CRUD operations tested.
- Image uploading and replacement tested.
- Existing image removal tested.
- Database image record deletion verified.
- Physical image file deletion verified.
- Multilingual interface tested in five languages.
- JSON translation files validated.
- Blade templates compiled successfully.
- Laravel Pint completed successfully: **174 files passed**.

**Result:** Watermark Management is implemented, tested, and integrated into the Catalog Management section.

### Part B - Sidebar Navigation

The application uses a structured sidebar to separate general catalog management from personal collection management.

#### Catalog Management

The **Catalog Management** section provides access to the shared catalog data used to identify and classify postage stamps.

The following modules are available:

| Module       | Description                                                          |
| ------------ | -------------------------------------------------------------------- |
| Countries    | Manage countries associated with stamps and collectibles.            |
| Catalogs     | Manage stamp catalogs and their editions.                            |
| Stamp Series | Organize stamps into series.                                         |
| Stamps       | Manage stamp information, catalog references, and associated images. |
| Themes       | Manage thematic classifications for stamps.                          |
| Collectibles | Manage collectible types and related information.                    |
| Watermarks   | Manage watermark information, translations, and images.              |

#### Personal Collection

The **Personal Collection** section provides access to the user's own stamp collection.

Currently available:

| Module | Description |

| My Stamps | Manage personally owned stamp copies and their associated information. |

The personal collection module supports managing individual stamp copies and their associated images.

Additional collection features, including catalog value calculations and historical value comparisons, are planned for future development.

User Interface

Both sections use the application's existing Flux UI sidebar components.

The navigation includes:

- Active menu-item highlighting.
- Consistent styling using Tailwind CSS.
- Responsive navigation for desktop and mobile devices.
- Multilingual navigation labels in Dutch, English, German, French, and Spanish.
- Visual separation between Catalog Management and Personal Collection.

**Status:** ✅ Completed

## Phase 14 — Catalog Values and Value History

**Status:** ⏳ Planned

Objective

Extend the stamp collection application with advanced catalog valuation features.

The goal is to manage catalog values for different stamp conditions, compare values between catalog editions, calculate the value of personal stamp collections, and visualize historical value development.

Phase 14 builds upon the existing Laravel 13 application, MariaDB database structure, and multilingual interface.

### Existing Foundation

The application already includes several components that support catalog valuations:

- `Catalog` — Stores stamp catalog information.
- `CatalogEdition` — Represents different catalog editions and publication years.
- `StampCatalogEntry` — Links stamps to catalog editions and catalog numbers.
- `StampCatalogValue` — Stores catalog values for specific stamp conditions.
- `Condition` — Defines stamp conditions such as mint and used.
- `StampCopy` — Represents individually owned stamp copies.
- `StampValuation` — Provides a foundation for stamp valuations.
- `StampCopyValuation` — Provides a foundation for valuations of owned stamp copies.
- `SeriesCatalogValue` — Supports catalog values for stamp series.
- `SeriesValuation` — Provides a foundation for stamp series valuations.

These existing components will be reviewed and reused wherever possible.

### Part A — Catalog Value Management

**Status:** ✅ Completed

**Objective:** Complete and improve the management of catalog values for individual stamps.

Planned tasks:

1. Review the existing catalog value database structure and relationships.
2. Review the current catalog value functionality in the stamp creation and editing forms.
3. Support catalog values for multiple catalog editions.
4. Manage values for different stamp conditions.
5. Allow existing catalog values to be updated.
6. Display catalog values consistently in stamp overview and detail pages.
7. Prevent duplicate catalog value records.
8. Add appropriate validation and automated tests.

**Expected result:** Catalog values can be managed reliably for different stamps, catalog editions, and conditions.

### Part B — Historical Catalog Values

**Status:** ✅ Completed

**Objective:** Compare catalog values across different editions of the same catalog.

Planned tasks:

1. Retrieve catalog values from multiple editions.
2. Compare values for the same stamp and condition.
3. Display historical catalog values in a structured overview.
4. Calculate absolute and percentage changes between editions.
5. Handle missing values and incomplete catalog history.
6. Ensure comparisons use consistent currencies.
7. Add automated tests.

**Expected result:** Users can compare how the catalog value of a stamp has changed over time.

### Part C — Personal Collection Valuation

**Status:** ✅ Completed — Implemented and Tested

**Objective:** Calculate the catalog value of personally owned stamps.

Implemented and verified features:

- Connect owned stamp copies to catalog values from a selected catalog edition.
- Determine the applicable catalog value based on stamp condition.
- Include owned quantities in value calculations.
- Calculate the total catalog value of the personal collection.
- Apply country and condition filters to valuation totals.
- Include only stamp copies belonging to the authenticated user.
- Keep catalog values in different currencies separate.
- Handle missing catalog values without interrupting calculations.
- Correctly distinguish catalog values of zero from missing values.
- Display catalog values for individual owned stamp copies.
- Calculate and display grouped catalog values per stamp series.
- Calculate and display grouped catalog values per country.
- Provide automated feature tests for catalog value calculations and grouped valuation displays.
- Complete final verification of Part C.

Automated valuation tests: 12 passed (28 assertions).

Full test suite: 188 passed (779 assertions).

Laravel Pint: PASS — 212 files.

**Final result:** The application calculates the catalog value of the authenticated user's personal stamp collection using the selected catalog edition, stamp conditions, owned quantities, and currencies.

In addition to the total collection value, the application provides grouped catalog valuations per stamp series and per country.

Valuation totals respect the selected filters, and catalog values in different currencies remain separate.

All automated tests and Laravel Pint checks passed successfully.

### Part D — Preferred Catalog and Edition

Status: ✅ Completed — Implemented and Tested

**Objective:** Allow each user to configure a preferred catalog and catalog edition for their personal stamp collection.

Implemented Features

- Users can select a default catalog and catalog edition through the application settings.
- Catalog preferences are stored individually for each authenticated user.
- The selected catalog edition is automatically applied when opening the personal stamp collection.
- Stamp copies are filtered according to the active catalog edition.
- Catalog valuations are calculated using the selected edition.
- Users can temporarily select another catalog or edition using the collection filters.
- Temporary selections do not overwrite the saved user preferences.
- Returning to the collection without temporary catalog filters restores the default edition.
- Existing catalog valuation functionality remains compatible with the preferred edition.

Technical Implementation

The functionality uses the existing Laravel application architecture.

Database and models:

- Added default_catalog_edition_id to the users table.
- Added the defaultCatalogEdition() relationship to the User model.
- Reused the existing Catalog and CatalogEdition models.

Settings:

- Added a dedicated Catalog Preferences settings page.
- Implemented catalog and catalog edition selection using Livewire Volt and Flux UI.
- Added validation to ensure that the selected edition belongs to the selected catalog.
- Added a navigation link to the application settings.
- Implemented persistence of the selected default catalog edition.

Personal collection:

- Updated StampCopyController to automatically use the user's preferred catalog edition.
- Applied the active catalog edition to stamp filtering and catalog valuation calculations.
- Preserved temporary catalog selections without modifying stored preferences.

Multilingual Support

The Catalog Preferences interface supports all five application languages:

- Dutch (nl)
- English (en)
- German (de)
- French (fr)
- Spanish (es)

**Final Result**

Users can configure their preferred catalog and catalog edition through the application settings.
The personal stamp collection automatically uses the saved edition for filtering and catalog valuation calculations.
Temporary catalog selections remain possible without changing the user's stored preferences.

Part D is implemented, tested, and ready for version control.

### Part E — Value History and Charts

**Status:** ⏳ Planned

**Objective:** Visualize historical catalog value development.

Planned tasks:

1. Display historical catalog values in tables.
2. Introduce charts showing value development across catalog editions.
3. Allow comparisons between stamp conditions.
4. Display absolute and percentage value changes.
5. Support filtering by catalog and stamp.
6. Handle missing historical data.
7. Ensure charts work with the existing application layout.

**Expected result:** Users can visually analyze changes in stamp catalog values over time.

### Part F — Multilingual Support

**Status:** ⏳ Planned

All new interfaces will support the application's five languages:

- Dutch (`nl`)
- English (`en`)
- German (`de`)
- French (`fr`)
- Spanish (`es`)

Translation keys will be added to the existing JSON translation files.

### Part G — Testing and Quality Assurance

**Status:** ⏳ Planned

Quality assurance will be performed throughout Phase 14.

Planned checks include:

1. Database relationship verification.
2. Form validation testing.
3. Feature tests for catalog value management.
4. Tests for historical value comparisons.
5. Tests for personal collection value calculations.
6. Tests for preferred catalog selection.
7. Multilingual interface verification.
8. Laravel Pint formatting checks.
9. PHPStan static analysis, where configured.
10. Full Laravel test suite execution.

### Part H — Personal Stamp Copy Valuation History

**Status**: ✅ Functionally Completed

**Objective**: Allow users to record and manage historical personal valuations of individual stamp copies.

Implemented features:

- Support multiple personal valuations for each stamp copy.
- Record valuation date, amount, currency, source, and notes.
- Display existing valuation records.
- Add, edit, and delete personal valuations.
- Provide a confirmation modal before deleting valuations.
- Implement appropriate validation and success messages.

**Result**: Users can maintain a history of manually recorded personal valuations for individual stamp copies.

These valuations are independent of automatic catalog value calculations.

### Part I — Catalog Condition Rules

**Status**: ✅ Functionally Completed

**Objective**: Allow users to configure valuation conditions and their applicable year ranges for individual catalogs.

Implemented features:

- Add valuation conditions to a catalog.
- Remove valuation conditions from the catalog creation form.
- Configure starting and ending years for each condition.
- Define the display order of valuation conditions.
- Save valuation conditions associated with a catalog.
- Restore and display the configured values correctly in the form.

**Result**: Users can configure catalog-specific valuation conditions with applicable year ranges and display order.

The functionality has been manually tested.

### Development Strategy

Phase 14 will be implemented incrementally.

Each part will follow the same development process:

1. Review existing functionality and database structures.
2. Identify the smallest required changes.
3. Implement one feature at a time.
4. Test the feature before proceeding.
5. Preserve existing functionality and data.
6. Update documentation when a part is completed.
7. Commit tested changes to Git.

### Completion Criteria

Phase 14 will be considered complete when:

- Catalog values can be managed across multiple editions and conditions.
- Historical catalog values can be compared.
- Personal collection values can be calculated.
- Preferred catalogs and editions can be selected.
- Historical value development can be visualized.
- All new interfaces support five languages.
- Required tests and quality checks pass.
- Documentation is updated.

---

fase 15
Toekomstige uitbreiding: catalogusgegevens in bulk bewerken
Stel dat je 1.000 Nederlandse postzegels hebt en een nieuwe NVPH-cataloguseditie voor 2027 toevoegt.

Dan zou je via een apart beheerscherm:

- Land: Nederland selecteren.
- Catalogus: NVPH selecteren.
- Editie: 2027 selecteren.
- Alle Nederlandse postzegels in één overzicht bekijken.
- Ontbrekende catalogusnummers en waarden voor Postfris en Gestempeld invullen.
- Alle wijzigingen in één handeling opslaan.
  Dat zou veel tijd besparen bij het verwerken van nieuwe catalogusjaargangen
