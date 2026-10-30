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

**Status:** ⏳ In progress — core functionality implemented, final verification and Git publication pending.

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
- [ ] Commit changes to Git and push them to GitHub.

### Acceptance Criteria

Phase 12 is complete when:

1. Stamp copies can be fully managed through the backend.
2. The condition and quantity of each stamp can be recorded.
3. Personal images can be associated with individual stamp copies.
4. Owned and missing stamps are clearly distinguishable.
5. All functionality works in all five supported languages.
6. All automated quality checks pass successfully.

**Status:** In progress — core functionality implemented, final verification and Git publication pending.
