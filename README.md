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

5 Language translations added: Dutch, English, German, Franch and Spanish

Validation added for these 5 languages.

Some small changes on the stamps views and stamp-series. To keep the validation-messages and field input field aligned.

Fase 5 completed
