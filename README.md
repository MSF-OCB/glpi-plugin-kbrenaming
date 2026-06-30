# GLPI plugin kbrenaming

`kbrenaming` normalizes software inventory entries that represent Microsoft KB patches.

Some GLPI inventories report Windows updates as standalone software records named `KB5034441`, `KB5021233`, and similar values. This plugin turns those raw KB software records into more useful GLPI software data: the KB number becomes a software version, while the software itself is named after the Microsoft update family.

## Purpose

The plugin avoids long software inventories made of isolated KB numbers. It groups Microsoft patches under a readable software name and keeps the KB number as the version.

Expected behavior example:

- inventory software received: `KB5034441`
- metadata is searched in the Microsoft Update Catalog
- a KB group is created or reused, usually matching a Windows update family
- a GLPI software record is created or reused for that family
- a GLPI software version named `KB5034441` is created or reused
- inventoried computers are linked to that normalized software version

## Compatibility

- GLPI: `>= 10.0.0` and `< 12.0.0`
- Intended for GLPI 10 and GLPI 11
- Syntax-checked with PHP 8.2

The technical plugin name is `kbrenaming`.

## Features

- Detects software names matching `KB` followed by at least six digits.
- Enriches unknown KB entries from the Microsoft Update Catalog.
- Creates missing KB records automatically.
- Groups KB patches by Microsoft update family.
- Creates GLPI software versions matching the KB numbers.
- Reassigns software installation relations to the normalized software version.
- Provides inventory hooks to adjust software data before GLPI stores it.
- Provides GLPI dropdowns to manage KB records and KB groups.
- Provides a report to analyze one KB by entity and operating system version.
- Includes console command classes for KB lookup and batch software normalization.

## Technical Behavior

### KB Detection

The plugin only processes names matching this pattern:

```text
KB123456
KB1234567
kb5034441
```

All other software names are ignored.

### Microsoft Update Catalog Lookup

When a KB is not already known in the plugin tables, the plugin queries:

```text
https://www.catalog.update.microsoft.com
```

The result is parsed to identify:

- the update title
- the Microsoft category
- the descriptive comment
- the update family to use as the GLPI software name

External calls use a timeout and a limited retry count so a slow or unavailable Microsoft service does not block inventory processing indefinitely.

### GLPI Normalization

When a `KBxxxxxx` software record is detected, the plugin:

1. finds or creates the KB entry in `glpi_plugin_kbrenaming_kbs`
2. finds or creates the KB group in `glpi_plugin_kbrenaming_kbgroups`
3. finds or creates the GLPI software matching the update family
4. finds or creates the software version matching the KB number
5. moves installation relations to the normalized software version
6. deletes the old standalone `KBxxxxxx` software record when it has been replaced

## Database Tables

### `glpi_plugin_kbrenaming_kbs`

Stores KB records known by the plugin.

Main fields:

- `name`: KB number, for example `KB5034441`
- `comment`: update description or title
- `plugin_kbrenaming_kbgroups_id`: related KB group/update family
- `disabled_update`: administrative flag

### `glpi_plugin_kbrenaming_kbgroups`

Stores update families used as GLPI software records.

Main fields:

- `name`: update family name
- `comment`: free text comment
- `softwarecategories_id`: related GLPI software category

## Installation

Copy the plugin into the GLPI plugins directory:

```text
glpi/plugins/kbrenaming
```

Install and activate it:

```bash
php bin/console plugin:install kbrenaming
php bin/console plugin:activate kbrenaming
```

In the MSF GLPI Docker image, the plugin is cloned from GitHub during the image build. The selected plugin revision is controlled by the Docker argument:

```dockerfile
ARG VERSION_PLUGIN_KBRENAMING=<commit>
```

After changing this plugin, push the new plugin commit and then update that Docker argument when the image must consume the new revision.

## Usage

### Automatic Inventory Processing

The primary use case is inventory normalization. When an agent or connector reports a software record named `KBxxxxxx`, the plugin normalizes the data during GLPI processing.

### Manual Administration

The plugin adds two dropdowns:

- `KB`
- `Groups of KB`

They can be used to review or adjust KB records and their groups.

### Report

The report `Summaries numbers computer by entries by OS version for one KB` analyzes the presence of one KB by entity and operating system version.

The report accepts a KB name and an entity, then displays totals by OS version.

### Console Commands

The code contains two console command classes:

```bash
php bin/console Kbrenaming:kb:finder KB5034441
php bin/console Kbrenaming:kb:rename_software
```

The first command looks up or creates data for one KB. The second command applies normalization to KB software records already present in the database.

If these commands do not appear in `php bin/console list`, verify their registration in the plugin console hooks before using them in production.

## Known Limits

- The plugin depends on Microsoft Update Catalog availability to enrich an unknown KB.
- If the Microsoft catalog is unavailable, the KB is skipped without a blocking error.
- Only names matching `KB` followed by at least six digits are processed.
- Software records that are not Microsoft KB patches are not modified.
- The plugin does not declare GLPI 12 compatibility.

## Security And Robustness

The plugin includes safeguards to reduce common failure modes:

- defensive validation of inventory payloads
- timeout and limited retries for network calls
- fallback when the PHP `shmop` extension is unavailable
- integer casting before DB operations
- GLPI DB API usage for critical update and delete operations
- escaped report output

## Maintenance

Before publishing a new version:

```bash
php -l setup.php
php -l hook.php
Get-ChildItem . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

After publishing the plugin commit, update the GLPI Docker image if needed.
