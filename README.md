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

*Status:** ✅ Completed

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

**Final Result** Users can configure their preferred catalog and catalog edition through the application settings.
The personal stamp collection automatically uses the saved edition for filtering and catalog valuation calculations.
Temporary catalog selections remain possible without changing the user's stored preferences.

Part D is implemented, tested, and ready for version control.

### Part E — Value History and Charts

**Status:** ✅ Completed

**Objective:** Visualize historical catalog value development.

Planned tasks:

1. Display historical catalog values on the stamp detail page.
2. Compare catalog values across multiple editions of the same catalog.
3. Visualize historical catalog values using Chart.js 4.6.1.
4. Display separate chart lines for different stamp conditions.
5. Support the valuation conditions Postfris, Ongebruikt met plakker, and Gestempeld.
6. Display catalog editions in chronological order.
7. Calculate absolute and percentage value changes between catalog editions.
8. Handle missing catalog values and incomplete historical data.
9. Prevent comparisons between different currencies.
10. Preserve the existing historical catalog value overview alongside the charts.
11. Integrate charts into the existing Laravel Blade and Tailwind CSS interface.

**Expected result:** Users can visually analyze changes in stamp catalog values over time.

### Part F — Multilingual Support

**Status:** ✅ Completed and Tested

All new interfaces support the application's five languages:

- Dutch (`nl`)
- English (`en`)
- German (`de`)
- French (`fr`)
- Spanish (`es`)

Implemented features:

1. Added multilingual support for historical catalog value charts.
2. Translated chart titles and accessibility labels.
3. Translated messages displayed when insufficient comparable catalog values are available.
4. Reused existing translations for stamp condition categories.
5. Integrated translations into the existing Laravel JSON translation system.
6. Added `scripts/translations_fase14_part_e.php` for managing the new translations.
7. Updated all five JSON translation files.

Validation and testing:

- All five JSON translation files validated successfully.
- All new translation keys verified in every language.
- Laravel translation functionality tested using Tinker.
- Chart titles and legends visually verified in all five languages.
- Historical catalog value interfaces visually verified.
- Git whitespace checks passed.

**Result:** Historical catalog value charts and their associated interface texts are available in all five supported languages.

**Git commit:** `2936e86` — `feat: add multilingual support for historical catalog value charts`

### Part G — Testing and Quality Assurance

**Status:** ✅ Completed and Tested

**Objective:** Verify the reliability, correctness, and code quality of the catalog valuation functionality implemented throughout Phase 14.

Completed quality assurance checks:

- Database relationships verified through automated model and feature tests.
- Form validation tested for catalog values, conditions, and personal valuations.
- Catalog value management verified through automated feature tests.
- Historical catalog value comparisons tested across editions, conditions, and currencies.
- Personal collection value calculations verified, including quantities, conditions, filters, and currencies.
- Preferred catalog edition selection tested, including temporary selections.
- Multilingual support verified for Dutch, English, German, French, and Spanish.
- Laravel Pint formatting checks completed successfully.
- PHPStan static analysis completed successfully at level 7.
- Full Laravel test suite executed successfully.

Automated test results:

- Full Laravel test suite: 191 tests passed (849 assertions).
- Historical catalog value tests: 8 passed (78 assertions).
- Catalog value management tests: 22 passed (104 assertions).
- Personal collection catalog valuation tests: 14 passed (37 assertions).
- Personal stamp copy valuation tests: 13 passed (39 assertions).
- Stamp translation and related feature tests: 33 passed (190 assertions).
- Historical catalog chart translation test: 1 passed (60 assertions).

Code quality results:

- Laravel Pint: 216 PHP files passed.
- PHPStan / Larastan: 155 files analyzed at level 7, with no errors.

Additional test coverage:

Added tests/Feature/Backend/StampCatalogChartTranslationTest.php.

This test verifies that the three historical catalog chart translation keys:

- Exist in all five supported language files
- Contain non-empty translations.
- Are correctly resolved through Laravel's translation helper.

**Result:** All executed automated tests and code quality checks passed successfully. No errors were detected during the final quality assurance checks.

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

### Phase 14 — ✅ Completed

## Phase 15 — Advanced Catalog Management & Bulk Operations

**Status:** ⏳ Planned

**Objective:** Improve the efficiency, reliability, and scalability of managing large stamp catalogs, catalog editions, catalog values, and personal collections.

Phase 15 focuses on bulk operations, catalog edition management, data import and export, data completeness, and change tracking.

The functionality introduced in this phase will build upon the catalog management, valuation, historical comparison, and multilingual features implemented in previous phases.

### Part A — Bulk Catalog Value Editing

**Status:** ⏳ Planned

**Objective:** Allow administrators to manage catalog numbers and catalog values for multiple stamps simultaneously.

Planned features:

- Select a country or territory.
- Select a catalog and catalog edition.
- Display all relevant stamps in a searchable and paginated overview.
- Add or update catalog numbers.
- Add or update catalog values for supported valuation conditions.
- Apply existing catalog condition rules and year restrictions.
- Filter stamps with missing catalog numbers or values.
- Highlight modified fields and unsaved changes.
- Validate changes before saving.
- Detect duplicate catalog numbers where applicable.
- Save multiple changes in a single operation.
- Display validation errors for individual records.
- Preserve existing data unless explicitly modified.-

**Example:** Update catalog numbers and mint/used values for 1,000 Dutch stamps when introducing the NVPH 2027 catalog edition.

**Result:** Administrators can efficiently maintain catalog information without editing each stamp individually.

### Part B — Catalog Edition Duplication

**Status:** ✅ Completed

**Objective:** Simplify the creation of new catalog editions by copying information from an existing edition.

Planned features:

- Select an existing catalog edition as the source.
- Select or create a destination catalog edition.
- Copy existing catalog numbers.
- Optionally copy catalog valuations.
- Copy only missing information when requested.
- Prevent unintended overwriting of existing data.
- Mark copied valuations for review.
- Validate copied data against applicable catalog condition rules.
- Display a summary before confirming the operation.
- Report successfully copied and skipped records.

**Example:** Create the NVPH 2027 catalog edition using catalog numbers and optionally valuations from NVPH 2026.

**Result:** New catalog editions can be prepared quickly while preserving existing catalog information.

### Part C — Catalog Data Completeness Dashboard

**Status:** ⏳ Planned

**Objective:** Provide an overview of missing and incomplete catalog information.

Planned features:

- Display the total number of relevant stamps.
- Count stamps with and without catalog numbers.
- Identify missing catalog valuations.
- Display completeness percentages.
- Filter results by country, catalog, and edition.
- Filter by valuation condition.
- Display incomplete records in a dedicated overview.
- Navigate directly to records requiring attention.
- Integrate with the bulk catalog editing interface.

**Result:** Administrators can monitor catalog completeness and efficiently identify missing information.

### Part D — Excel and CSV Import

**Status:** ⏳ Planned

**Objective:** Allow catalog information to be imported from external spreadsheet files.

Planned features:

- Support CSV and Excel file imports.
- Select the destination catalog and edition.
- Map spreadsheet columns to application fields.
- Preview imported records before processing.
- Validate catalog numbers and valuations.
- Detect duplicate or conflicting records.
- Identify missing or invalid references.
- Allow administrators to choose how existing records are handled.
- Prevent unintended overwriting of existing data.
- Process large imports efficiently.
- Display import results, including successful, skipped, and failed records.

**Result:** Large amounts of catalog information can be imported efficiently and safely.

### Part E — Excel and CSV Export

**Status:** ⏳ Planned

**Objective:** Allow administrators to export catalog information for external processing, reporting, and backup purposes.

Planned features:

- Export catalog data to CSV or Excel.
- Filter exports by country or territory.
- Filter by catalog and edition.
- Export catalog numbers and valuations.
- Export selected stamps or entire filtered datasets.
- Export incomplete catalog records.
- Preserve a consistent column structure.
- Support files that can be edited and imported again.

**Result:** Catalog information can be reviewed, processed, and exchanged outside the application.

### Part F — Catalog Edition Comparison

**Status:** ⏳ Planned

**Objective:** Compare catalog valuations between different editions of the same catalog.

Planned features:

- Select two catalog editions for comparison.
- Compare catalog values for individual stamps.
- Calculate absolute and percentage differences.
- Highlight increases and decreases.
- Identify unchanged catalog values.
- Filter comparisons by valuation condition.
- Sort results by valuation changes.
- Handle missing values appropriately.
- Avoid invalid comparisons between different currencies.
- Export comparison results.
- Reuse existing historical catalog valuation functionality where appropriate.

**Result:** Administrators can identify valuation changes between catalog editions and analyze catalog trends.

### Part G — Catalog Change History

**Status:** ⏳ Planned

**Objective:** Track changes to catalog numbers and catalog valuations.

Planned features:

- Record changes to catalog numbers.
- Record changes to catalog values.
- Store previous and updated values.
- Record the date and time of each change.
- Identify the user responsible for a change.
- Group related changes performed during bulk operations.
- Display change history for individual stamps.
- Display change history for catalog editions.
- Allow administrators to review previous changes.
- Investigate the feasibility of safely reversing bulk operations.

**Result:** Catalog changes become traceable, improving transparency and data reliability.

### Part H — Bulk Stamp Management

**Status:** ⏳ Planned

**Objective:** Allow administrators to update general stamp information for multiple stamps simultaneously.

Planned features:

- Select multiple stamps from a filtered overview.
- Update countries or territories where appropriate.
- Update issue years.
- Assign or change stamp series.
- Add or remove themes.
- Update printing methods.
- Fill missing stamp information.
- Validate relationships and existing restrictions.
- Preview changes before saving.
- Prevent unintended modifications.

**Result:** General stamp information can be maintained efficiently across large datasets.

### Part I — Bulk Personal Collection Management

**Status:** ⏳ Planned

**Objective:** Allow users to manage multiple personal collection items in a single operation.

Planned features:

- Select multiple stamps.
- Add multiple stamps to a personal collection.
- Specify quantities.
- Select valuation conditions.
- Record acquisition dates where supported.
- Investigate support for purchase prices.
- Update multiple collection items.
- Remove selected collection items with confirmation.
- Respect existing ownership and authorization rules.
- Preserve support for special variants and deviations.

**Result:** Users can manage larger personal collections without processing each stamp individually.

### Part J — Catalog Data Integrity Checks

**Status:** ⏳ Planned

**Objective:** Detect inconsistencies and incomplete relationships within catalog information.

Planned features:

- Identify duplicate catalog numbers where applicable.
- Detect catalog entries with missing required information.
- Identify missing valuation conditions.
- Validate catalog condition year restrictions.
- Verify country and catalog edition relationships.
- Identify incomplete catalog valuations.
- Display detected issues in a dedicated overview.
- Provide direct navigation to affected records.
- Reuse existing validation rules wherever possible.

**Result:** Administrators can proactively identify potential catalog data problems and maintain reliable records.

### Part K — Testing and Quality Assurance

**Status:** ⏳ Planned

**Objective:** Ensure that all new bulk management functionality is reliable, secure, and maintainable.

Planned checks include:

- Database relationship verification.
- Bulk operation validation tests.
- Catalog edition duplication tests.
- Import and export tests.
- Catalog completeness calculation tests.
- Catalog edition comparison tests.
- Change history verification.
- User authorization and data isolation tests.
- Large dataset and performance testing.
- Multilingual interface verification.
- Laravel Pint formatting checks.
- PHPStan static analysis.
- Full Laravel test suite execution.

**Result:** All new functionality will be tested before Phase 15 is marked as completed.

### Development Strategy

The implementation will follow a structured, incremental approach.

**Initial development order:**

1. Part B — Catalog Edition Duplication.
2. Part A — Bulk Catalog Value Editing.
3. Part C — Catalog Data Completeness Dashboard.

**Subsequent development:**

- Part D — Excel and CSV Import.
- Part E — Excel and CSV Export.
- Part F — Catalog Edition Comparison.
- Part G — Catalog Change History.
- Part H — Bulk Stamp Management.
- Part I — Bulk Personal Collection Management.
- Part J — Catalog Data Integrity Checks.
- Part K — Testing and Quality Assurance will be performed throughout the development process and finalized after all planned functionality has been implemented.

### Technical Considerations

- Use Laravel 13, Eloquent, Livewire, and Flux UI components.
- Maintain the existing application design and styling conventions.
- Support the five existing application languages.
- Reuse existing models, relationships, and validation rules.
- Avoid loading large datasets into a single Livewire component.
- Use pagination, filtering, and efficient database queries.
- Consider database transactions for multi-record operations.
- Consider queued jobs for large imports and bulk processing.
- Prevent unauthorized access to other users' collection data.
- Preserve existing catalog information unless changes are explicitly confirmed.
- Keep new functionality compatible with existing catalog editions, valuation conditions, and historical valuation features.

**Overall Goal:** Provide a scalable and user-friendly catalog management system capable of efficiently handling thousands of stamps and multiple catalog editions.

### Centralized Currency Management

Status: ⏳ Planned

**Objective:** Centralize currency management across the application, including support for historical currencies and multilingual currency names.

Existing implementation:

- Currency and CurrencyTranslation models are already available.
- Database migrations for currencies and currency translations exist.
- Several forms already contain currency dropdowns.
- Some dropdowns already support historical currencies, including the Dutch guilder (NLG).

Planned improvements:

- Reuse and extend the existing currencies table.
- Support both current and historical currencies.
- Use currency translations for multilingual display names.
- Develop a reusable currency dropdown component.
- Allow catalog editions to use their original currency, such as NLG or EUR.
- Reuse the centralized currency selection for catalog values, purchases and personal valuations.
- Investigate user-specific preferred currencies and optional currency conversion.
- Preserve existing currency data during future database changes.

**Priority:** Future development phase. No implementation changes required at this stage.
