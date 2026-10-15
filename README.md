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

php artisan make:migration create_countries_table
php artisan make:migration create_themes_table
php artisan make:migration create_conditions_table
php artisan make:migration create_stamp_series_table
php artisan make:migration create_stamps_table
php artisan make:migration create_stamp_themes_table
php artisan make:migration create_stamp_copies_table

Fase 1 is now completed

## Fase 2

Once this phase is working, we will move on to:

- Catalogs: Michel, NVPH, Yvert & Tellier, and other catalogs.
- Catalog editions: individual annual editions containing historical catalog values.
- Value history: the price trajectory of stamps, both for individual items and complete sets.
- Images: multiple images per stamp and per individual item.
- User collections: allowing each user to manage their own collection and catalog holdings.

Following that, we will create the Eloquent models and define the relationships, enabling Laravel to easily retrieve and store the data.

step 1:

The next step is the catalogue system. We are designing this from the outset to support multiple users, distinct catalogues for different countries, and historical catalogue values

In doing so, we keep three elements strictly separate:

- Catalogue data: which stamp appears in which catalogue.
- Catalogue editions: the data and values ​​associated with a specific edition.
- Catalogue holdings: which editions a user personally owns.

php artisan make:migration create_catalogs_table
php artisan make:migration create_catalog_countries_table
php artisan make:migration create_catalog_editions_table
php artisan make:migration create_stamp_catalog_values_table
php artisan make:migration create_series_catalog_values_table
php artisan make:migration create_user_catalogs_table

step 2:

Next, we are adding value history. In doing so, we make an important distinction between three types of valuation:

1. Stamp value: the general value of a specific stamp, for example, based on a current market assessment.
2. Individual item value: the estimated value of your specific specimen, taking into account its condition, rarity, and any printing errors.
3. Series value: the value of a complete set of stamps.

Catalog values ​​will remain in the existing tables. This will allow us to create charts later on that display catalog values ​​and other types of valuations separately.

php artisan make:migration create_stamp_valuations_table
php artisan make:migration create_stamp_copy_valuations_table
php artisan make:migration create_series_valuations_table

step 3:

Once the migrations have been successfully completed, we build the image structure.
In doing so, we support:

- Multiple images per postage stamp.
- Separate photos of individual specimens.
- Front, back, and detail photos.
- Relative file paths in MariaDB.
- Files organized by country and year of issue.

php artisan make:migration create_stamp_images_table
php artisan make:migration create_stamp_copy_images_table

Fase 2 is completed.

## Fase 3 Eloquent models and relationships

This phase is important: it allows Laravel, for example, to retrieve all stamps from a specific country, display individual copies of a particular stamp, link catalogue values, and look up valuation history.
Each physical copy is assigned its own record, enabling you to register rare variants and printing errors separately.

php artisan make:model Country
php artisan make:model Theme
php artisan make:model Condition
php artisan make:model StampSeries
php artisan make:model Stamp
php artisan make:model StampCopy
php artisan make:model StampImage
php artisan make:model StampCopyImage
php artisan make:model Catalog
php artisan make:model CatalogEdition
php artisan make:model StampCatalogValue
php artisan make:model SeriesCatalogValue
php artisan make:model UserCatalog
php artisan make:model StampValuation
php artisan make:model StampCopyValuation
php artisan make:model SeriesValuation

We now move on to populating the database with basic data using seeders. This saves you from having to enter general information manually.
We will start with three tables:

- Countries: the countries from which you collect stamps.
- Themes: subjects such as animals, sports, art, and architecture.
- Conditions: the condition categories for your stamps.

php artisan make:seeder CountrySeeder
php artisan make:seeder ThemeSeeder
php artisan make:seeder ConditionSeeder

Fase 3 is completed
