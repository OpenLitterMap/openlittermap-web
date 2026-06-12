# Data Export System

CSV export for users, teams, and locations. Queued via Maatwebsite/Excel to S3, then an email with a download link is sent to the user.

## Architecture

```
User/Team/Location triggers export
    ↓
Controller validates auth + builds date filter
    ↓
CreateCSVExport queued to S3 (Maatwebsite/Excel FromQuery)
    ↓
EmailUserExportCompleted job chains → sends ExportWithLink mailable
    ↓
User receives email with S3 download URL
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Exports/CreateCSVExport.php` | Core CSV export class (headings, mapping, query) |
| `app/Actions/Teams/DownloadTeamDataAction.php` | Orchestrates team export dispatch |
| `app/Http/Controllers/User/ProfileController.php` | User export endpoint (`download()`) |
| `app/Http/Controllers/API/TeamsController.php` | Team export endpoint (`download()`) |
| `app/Http/Controllers/DownloadControllerNew.php` | Location-based export endpoint |
| `app/Jobs/EmailUserExportCompleted.php` | Queued job — sends download email |
| `app/Mail/ExportWithLink.php` | Mailable with S3 URL |
| `config/excel.php` | Maatwebsite/Excel config (chunk size: 1000) |

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/views/User/Uploads/components/UploadsHeader.vue` | Export CSV button on Uploads page |
| `resources/js/views/Teams/TeamsHub.vue` | Export CSV button on Teams page |
| `resources/js/stores/teams/index.js` | `downloadTeamData()` store action |

### Tests

| File | Coverage |
|------|----------|
| `tests/Unit/Exports/CreateCSVExportTest.php` | Headings, mapping, materials aggregation, brands format, types from DB, null values |
| `tests/Feature/Teams/DownloadTeamDataTest.php` | Member auth, non-member rejection, date filter |

## API Endpoints

### GET /api/user/profile/download — User Data Export

**Auth:** Required (Sanctum)

**Query params (all optional):**

| Param | Type | Description |
|-------|------|-------------|
| `dateField` | string | Column to filter: `created_at`, `datetime`, or `updated_at` |
| `fromDate` | string | Start date (YYYY-MM-DD). Default: `2017-01-01` |
| `toDate` | string | End date (YYYY-MM-DD). Default: today |
| `format` | string | Comma-separated subset of `split,joined`. Default `split`. See [CSV Format](#csv-format) below. Ignored when `layout=long`. |
| `layout` | string | `wide` (default) or `long`. See [Wide vs Long Layout](#wide-vs-long-layout). |

**Response:** `{ "success": true }`

Exports **all** user photos (any verification status). Email sent when ready.

### POST /api/teams/download — Team Data Export

**Auth:** Required (Sanctum). Must be a team member (any role).

**Request body:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `team_id` | int | Yes | Team ID |
| `dateField` | string | No | Column to filter: `created_at`, `datetime`, or `updated_at` |
| `fromDate` | string | No | Start date (YYYY-MM-DD). Default: `2017-01-01` |
| `toDate` | string | No | End date (YYYY-MM-DD). Default: today |
| `tag` | string | No | Filter by tag name (partial match on litter object key) |
| `custom_tag` | string | No | Filter by custom tag (partial match) |
| `picked_up` | string | No | `true` or `false` — filter by picked-up status |
| `member_id` | int | No | Filter by team member's user ID |
| `status` | string | No | `pending`, `approved`, or `all` (team approval status) |
| `format` | string | No | Comma-separated subset of `split,joined`. Default `split`. See [CSV Format](#csv-format) below. Ignored when `layout=long`. |
| `layout` | string | No | `wide` (default) or `long`. See [Wide vs Long Layout](#wide-vs-long-layout). |

**Response:** `{ "success": true }`
**Error:** `{ "success": false, "message": "not-a-member" }`

All team members can export. Exports only `verified >= ADMIN_APPROVED` team photos (includes BBOX_APPLIED, BBOX_VERIFIED, AI_READY). Extra filters narrow the export scope. Email sent when ready.

### POST /api/download — Location Data Export

**Auth:** Optional (uses `email` param if unauthenticated)

**Request body:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `locationType` | string | Yes | `country`, `state`, or `city` |
| `locationId` | int | Yes | Location ID |
| `email` | string | No | Required if unauthenticated |
| `format` | string | No | Comma-separated subset of `split,joined`. Default `split`. See [CSV Format](#csv-format) below. Ignored when `layout=long`. |
| `layout` | string | No | `wide` (default) or `long`. See [Wide vs Long Layout](#wide-vs-long-layout). |

**Response:** `{ "success": true }`

Exports only `verified >= ADMIN_APPROVED` photos for the location.

## CSV Structure

> **⚠ Column positions are not stable across format modes.** Selecting `split`, `joined`, or `split,joined` changes which blocks appear and how many columns precede each section. Always reference columns by **header name**, not index, in downstream scripts.
>
> Categories, objects, materials, and types are sorted **A-Z by key** (deterministic) so column positions within a single format mode are stable when the underlying tag set doesn't change.

**Block order (per mode):**

| Block | `split` | `joined` | `split,joined` |
|-------|:-------:|:--------:|:--------------:|
| Fixed cols (10) | always | always | always |
| Split categories (per-category, A-Z) | ✓ | — | ✓ |
| TYPES | ✓ | — | ✓ |
| MATERIALS | ✓ | ✓ | ✓ |
| Joined categories (per-category, A-Z) | — | ✓ | ✓ |
| brands | when present | when present | when present |
| custom_tag_1/2/3 | when present | when present | when present |

The CSV has four sections:

### 1. Fixed columns (10)

| Column | Source |
|--------|--------|
| `id` | `photos.id` |
| `verification` | `photos.verified` (VerificationStatus enum value) |
| `phone` | `photos.model` (device model) |
| `date_taken` | `photos.datetime` |
| `date_uploaded` | `photos.created_at` |
| `lat` | `photos.lat` |
| `lon` | `photos.lon` |
| `picked up` | Inverted from `photos.remaining` (`Yes`/`No`) |
| `address` | `photos.display_name` accessor (derived from `address_array` JSON) |
| `total_tags` | `photos.total_tags` (sum of objects + materials + brands + custom — set by `GeneratePhotoSummaryService`) |

### 2. Category/object columns (~180 dynamic)

One separator column per category (uppercase key, always null in data rows), then one column per litter object in that category. Value = aggregated quantity from `summary.tags.{catId}.{objId}.quantity`.

```
ALCOHOL, can, bottle, wrapper, ...
SMOKING, butts, lighters, ...
FOOD, sweetwrappers, ...
...
```

### 3. Materials, types, and brands columns (~76)

| Section | Columns | Source | Format |
|---------|---------|--------|--------|
| Materials | `MATERIALS` separator + 40 material keys | `summary.tags[*].materials` (array of material IDs) | Integer quantity per material (inherits parent tag quantity), aggregated across all tags |
| Types | `TYPES` separator + 33 type keys | `summary.tags[*].type_id` | Integer quantity per type, aggregated across all tags |
| Brands | Single `brands` column | `summary.tags[*].brands` (`{brandId: qty}`) + `summary.keys.brands` for name resolution | Semicolon-delimited string: `brandname:qty;brandname:qty` |

**Why brands are a single column:** There are 2,600+ brands in the database. One column per brand would make the CSV unusable. The delimited format is parseable with Excel `TEXTSPLIT()` or Python `str.split(';')`.

### 4. Custom tags (3 fixed)

| Column | Source |
|--------|--------|
| `custom_tag_1` | First custom tag key from `photo_tags.extraTags` where `tag_type = 'custom_tag'` |
| `custom_tag_2` | Second custom tag key |
| `custom_tag_3` | Third custom tag key |

Extracted from the eager-loaded `photoTags.extraTags.extraTag` relationship. Limited to 3 columns for backward compatibility.

## Summary JSON Structure

The `map()` method reads from the `photos.summary` JSON (v5.1 flat array format):

```json
{
    "tags": [
        {
            "clo_id": 152,
            "category_id": 16,
            "object_id": 5,
            "type_id": 24,
            "quantity": 1,
            "picked_up": true,
            "materials": [5],
            "brands": {"77": 1},
            "custom_tags": [321]
        }
    ],
    "totals": {
        "litter": 1,
        "materials": 1,
        "brands": 1,
        "custom_tags": 1
    },
    "keys": {
        "categories": { "16": "softdrinks" },
        "objects": { "5": "can" },
        "types": { "24": "soda" },
        "materials": { "5": "aluminium" },
        "brands": { "77": "pepsi" },
        "custom_tags": { "321": "bn:Alani Nu" }
    }
}
```

- **Tags**: Flat array — each entry has `category_id`, `object_id`, `type_id`, `quantity`
- **Materials**: Array of material IDs `[5]` — quantity is inherited from parent tag
- **Brands**: `{ brandId: quantity }` objects with independent quantities
- **Custom tags**: Array of custom tag IDs `[321]` — quantity inherited from parent tag
- **type_id**: Present in the summary (can be null) — used for type column mapping
- **Keys**: Human-readable name lookups by ID (used for brand name resolution in CSV)

## Date Filter Plumbing

Both user and team exports support the same date filter contract:

```php
$dateFilter = [
    'column' => 'datetime',     // or 'created_at' or 'updated_at'
    'fromDate' => '2025-01-01', // YYYY-MM-DD
    'toDate' => '2025-12-31',   // YYYY-MM-DD
];
```

- **User exports** (`ProfileController::download()`): Parses from query params. Defaults: `fromDate = 2017-01-01`, `toDate = now()`.
- **Team exports** (`TeamsController::download()`): Parses from request body. Same defaults. Whitelists `dateField` to `created_at`, `datetime`, `updated_at`.
- **`CreateCSVExport`**: Applies via `whereBetween($column, [$fromDate, $toDate])` in `query()`.

## S3 Path Patterns

| Scope | Path |
|-------|------|
| User (no filter) | `YYYY/MM/DD/UNIX_MyData_OpenLitterMap.csv` |
| User (date filter) | `YYYY/MM/DD/UNIX_from_DATE_to_DATE_MyData_OpenLitterMap.csv` |
| Team (no filter) | `YYYY/MM/DD/UNIX/_Team_OpenLitterMap.csv` |
| Team (date filter) | `YYYY/MM/DD/UNIX_from_DATE_to_DATE/_Team_OpenLitterMap.csv` |
| Location | `YYYY/MM/DD/UNIX/{LocationName}_OpenLitterMap.csv` |

## Frontend Integration

### Uploads Page (`UploadsHeader.vue`)

- "Export CSV" button next to the "Apply" filter button
- Sends `GET /api/user/profile/download` with `dateField: 'datetime'` and the current `dateFrom`/`dateTo` filter values
- Shows inline success message: "Export started — check your email for the download link."

### Teams Photos Tab (`TeamPhotosHeader.vue` + `TeamPhotoList.vue`)

- Rich filter bar in the Photos tab with: status (All/Pending/Approved), tagged/untagged, picked up, photo ID, tag search, custom tag, member dropdown, date range, per page
- "Export CSV" button sends current filters to `POST /api/teams/download`
- All team members can export (not restricted to leaders/school managers)
- Filter bar emits `@apply` (refresh grid) and `@export` (download CSV) events
- Uses dark glass theme (`bg-white/5 border-white/10`, emerald accents)

### Teams Store (`stores/teams/index.js`)

```js
async downloadTeamData(teamId, filters = {}) {
    const payload = { team_id: teamId };
    if (filters.date_from) { payload.dateField = 'datetime'; payload.fromDate = filters.date_from; }
    if (filters.date_to) { payload.dateField = 'datetime'; payload.toDate = filters.date_to; }
    if (filters.tag) payload.tag = filters.tag;
    if (filters.custom_tag) payload.custom_tag = filters.custom_tag;
    if (filters.picked_up && filters.picked_up !== 'all') payload.picked_up = filters.picked_up;
    if (filters.member_id) payload.member_id = filters.member_id;
    if (filters.status && filters.status !== 'all') payload.status = filters.status;
    await axios.post('/api/teams/download', payload);
}
```

## Query Scoping Rules

| Export Type | Verification Filter | Auth | Photos Included |
|-------------|-------------------|------|-----------------|
| User | None | Any authenticated user (own data) | All photos (any status) |
| Team | `verified >= ADMIN_APPROVED` | Any team member | Approved photos (ADMIN_APPROVED, BBOX_APPLIED, BBOX_VERIFIED, AI_READY) + extra filters |
| Location | `verified >= ADMIN_APPROVED` | Optional (email param for guests) | Approved photos (same as above) |

School team photos with `is_public = false` are excluded because teacher approval is required to reach `ADMIN_APPROVED`, and approval also sets `is_public = true`.

## CSV Format

The download UI exposes three radio options: **OLM Original Download (many columns)** (default → `format=split`), **OLM 4.0 compatible** (`format=joined`), and **OLM New Download (fewer columns)** (`layout=long`, ignores `format` — see [Wide vs Long Layout](#wide-vs-long-layout)). The `format` parameter therefore only takes effect in the wide layout.

The `format` parameter is comma-separated, case-insensitive, deduped. Empty / unrecognized → `split` (OLM Original Download).

Controllers (`ProfileController::download`, `TeamsController::download`, `DownloadControllerNew::index`) all parse this through `CreateCSVExport::parseFormats(?string $raw)` — the single source of truth for splitting, normalizing, and validating the param.

Block composition per mode is defined authoritatively by the block-order table under [CSV Structure](#csv-structure) above.

| UI label | `format` value | Block mode | Use when |
|----------|---------------|------------|----------|
| OLM Original Download (default) | `split` | v5 layout — per-category split block + `TYPES` | You want one column per dimension; downstream pivots/joins on the underlying schema. |
| OLM 4.0 compatible | `joined` | v4-style — per-category `{type}_{object}` joined block; suppresses the split block and `TYPES` | Your pre-v5 pipeline expects `spirits_bottle`/`beer_can`/etc. as single columns. |
| _(API only — not exposed in UI)_ | `split,joined` | Both blocks (split first, then joined); `MATERIALS` once | You want the v5 layout for new analysis but also need v4-compatible columns in the same file. |

**Joined column key generation.** For each distinct `(category_id, litter_object_id, litter_object_type_id)` triple in the export scope, the column key is `{type_key}_{object_key}` when a type is set, else the bare `{object_key}` (e.g. `spirits_bottle`, `bottle`). Per-category `ALCOHOL`/`SOFTDRINKS`/etc. sub-headers separate sections so the same bare key under two categories does not collide visually. Materials are **not** collapsed into the object key (that would explode column count) — the MATERIALS block still carries per-material totals.

### Cell values are verbatim — treat free-text columns as untrusted in spreadsheets

Exports are **research data, consumed programmatically** (pandas / R / `csv`), so cell
values are written **byte-exact**. We deliberately do **not** prefix or otherwise alter
values to defend against spreadsheet "formula injection."

The writer (PhpSpreadsheet via Maatwebsite/Excel) quotes every cell, which prevents
delimiter/quote/newline breakout — the only part that would corrupt a CSV *parser*. It
does not neutralize a value that begins with `=`, `+`, `-`, or `@`, because there is no
in-band CSV signal for "text, not formula." Any defusing prefix (`'`, tab, space) would
become part of the value that pandas/R read back, silently mangling the data — and a
blanket guard would also rewrite legitimate values such as **negative longitudes**
(`-77.154`). For a programmatically-consumed dataset that trade is not worth it.

**Free-text columns to treat as untrusted** if you open an export in a spreadsheet app
instead of parsing it: `custom_tag_*`, `model`, and `display_name` (user- or
externally-sourced text). This is the same caution you would apply to **any** third-party
CSV. Programmatic consumers are unaffected — they never evaluate formulas.

> **Recommended path for spreadsheet users (backlog, not yet built):** an `.xlsx` export
> that types these cells as explicit strings, so Excel will not evaluate them — safe by
> construction, with CSV kept as the verbatim research format. See
> [Backlog](#backlog) below.

### Enum-safe value binder

`CreateCSVExport` is its own PhpSpreadsheet value binder (`extends DefaultValueBinder
implements WithCustomValueBinder`). PhpSpreadsheet's default binder `(string)`-casts any
unrecognised object reaching a cell — which **fatals** on a non-`Stringable` backed enum
and 500s the entire export. Several `Photo` attributes are enum casts (most notably
`verified` → `App\Enums\VerificationStatus`), so the override scalarises enums before
binding: `BackedEnum` → `->value`, `UnitEnum` → `->name`. Everything else (ints, strings,
Carbon dates, null) passes through to the default binder unchanged.

This is **belt-and-suspenders**: `map()`/`mapLong()` already emit `verified?->value`, but
the binder guarantees that *any* column accidentally emitting an enum (now or after a
future schema change) is written safely instead of crashing the export. The historical
crash — `Object of class App\Enums\VerificationStatus could not be converted to string`
at `DefaultValueBinder.php:30` — predated the `->value` map fix; the binder closes the
class of bug for good. Guarded by `CreateCSVExportFormatTest` (real `raw()` writer
pipeline + a direct `bindValue()` unit test) and `CreateCSVExportLongFormatTest`. Note the
`map()`-only tests never exercise the writer, so the **real-pipeline** tests are what
actually cover this.

## Wide vs Long Layout

> **Naming note:** the API calls these `wide` and `long`. In the download UI, **wide** is used by both the **OLM Original Download** and **OLM 4.0 compatible** options, and **long** is used by **OLM New Download**. Saved filenames use `_number-based_` (wide) / `_full-detail_` (long) slugs. Internal code, tests, and the API/developer docs below keep `wide`/`long`.

The `layout` query/body parameter chooses the row shape of the CSV. Two values: `wide` (default) and `long`. Parsed by `CreateCSVExport::parseLayout(?string $raw)` — the single source of truth — and passed as the 8th constructor argument to `CreateCSVExport`.

| Layout (API) | UI label(s) | What it emits | Use when |
|--------------|-------------|---------------|----------|
| `wide` (default) | OLM Original Download / OLM 4.0 compatible | One row per photo. Hundreds of columns, one per possible tag value. Honours `format=split,joined`. | Eyeballing in Excel; matches the historical OpenLitterMap export. |
| `long` | OLM New Download | One row per tag dimension. 14 fixed columns. **Ignores `format`** (no split/joined blocks). | Loading into pandas, SQL, Tableau, R — anywhere you'd `groupby` / `pivot_table` afterwards. |

### Full-detail (long) layout columns (14)

| # | Column | Source | Notes |
|---|--------|--------|-------|
| 1 | `photo_id` | `photos.id` | |
| 2 | `datetime` | `photos.datetime` | |
| 3 | `lat` | `photos.lat` | |
| 4 | `lng` | `photos.lon` | Heading is `lng` (long-format spec); value comes from the wide-format `lon` column. |
| 5 | `team` | `photos.team->name` | Empty if photo has no team. |
| 6 | `verification` | `photos.verified` | VerificationStatus enum value. |
| 7 | `category` | `summary.keys.categories[category_id]` | Empty when PhotoTag has no category (extras-only rows). |
| 8 | `object` | `summary.keys.objects[litter_object_id]` | Empty for brand-only / material-only / custom-only PhotoTags. |
| 9 | `type` | `summary.keys.types[litter_object_type_id]` | Empty when `type_id` is null. |
| 10 | `material` | `summary.keys.materials[tag_type_id]` | Populated only on material rows. |
| 11 | `brand` | `summary.keys.brands[tag_type_id]` | Populated only on brand rows. |
| 12 | `custom_tag` | `extraTag.key` (relation walk) | Populated only on custom-tag rows. |
| 13 | `quantity` | parent or per-extra qty (see below) | |
| 14 | `photo_tag_id` | `photo_tags.id` | **Use to dedupe before SUM** — see worked example. |

### Row emission rules

For each PhotoTag attached to the photo:

- **Object PhotoTag** (`litter_object_id IS NOT NULL`): emit one **bare-object row** (object/type populated, all extras empty, `qty = $pt->quantity`) plus one row per material extra (qty = parent qty), one row per brand extra (qty = brand-specific qty from the extra row), one row per custom_tag extra (qty = 1).
- **Brand-only PhotoTag** (`litter_object_id IS NULL`, has brand extras): one row per brand, only `brand` populated, qty = brand-specific qty. **No** bare-object row.
- **Material-only PhotoTag**: one row per material, only `material` populated, qty = parent qty.
- **Custom-tag-only PhotoTag**: one row per custom_tag, only `custom_tag` populated, qty = 1.
- **Photo with no PhotoTags**: emits zero rows.

Rows are **per-extra, not cartesian** — a PhotoTag with 5 materials and 56 brands emits 1 + 5 + 56 = 62 rows (not 5 × 56 = 280). The `photo_tag_id` column lets analysis pipelines dedupe correctly when summing.

> ⚠ **Don't naively `SUM(quantity)`.** Material rows replicate their parent PhotoTag's quantity, so summing every row in a category overcounts by ~Nx materials. Either filter to bare-object rows (`material = '' AND brand = '' AND custom_tag = ''`) before summing, or `GROUP BY photo_tag_id` first. See the worked example below.

### `username` deliberately excluded

The long-format schema does not include a `username` column in v1. School teams use safeguarding pseudonyms elsewhere on the platform, and the simplest privacy posture for a CSV that may be saved/shared off-platform is to omit the column entirely. Teams that need per-user analysis can join the CSV against their own roster via `photo_id` (admins) or the team's own member list.

### Worked example

A single photo with one object PhotoTag — `bottle, beer, glass, qty=3` — plus brand extras `{coca_cola: 2, pepsi: 1}`.

**Number-based layout** (`layout=wide`, one row, ~200+ columns shown abbreviated):

```
id  ... ALCOHOL  bottle  TYPES  beer  MATERIALS  glass  brands
42  ... null     3       null   3     null       3      coca_cola:2;pepsi:1
```

**Full-detail layout** (`layout=long`, 4 rows, 14 columns each):

```
photo_id  datetime ... category  object  type  material  brand       custom_tag  quantity  photo_tag_id
42        ...      ... alcohol   bottle  beer  ""        ""          ""          3         77   ← bare object
42        ...      ... alcohol   bottle  beer  glass     ""          ""          3         77   ← material row
42        ...      ... alcohol   bottle  beer  ""        coca_cola   ""          2         77   ← brand row, brand qty
42        ...      ... alcohol   bottle  beer  ""        pepsi       ""          1         77   ← brand row, brand qty
```

Naive `SUM(quantity)` across all four rows gives 9 (overcount). Correct dedup approaches:

- Recover the parent qty: `WHERE material='' AND brand='' AND custom_tag=''` → 3.
- Per-brand totals: `WHERE brand='coca_cola'` → 2.
- Per-material totals: `WHERE material='glass'` → 3.
- Total objects per photo: `GROUP BY photo_tag_id, MAX(quantity) WHERE material='' AND brand='' AND custom_tag=''`.

### Tests

See `tests/Feature/Exports/CreateCSVExportLongFormatTest.php` (13 cases) for the full row-emission contract.

## v4 → v5 Column Layout Notes

**Subtype split.** v4 had per-subtype columns (`spiritBottle`, `beerBottle`, `wineBottle`, `tinCan`, `fizzyDrinkBottle`, `waterBottle`, etc.). v5 splits these across two sections:

| v4 column | v5 columns |
|-----------|------------|
| `spiritBottle` | `ALCOHOL.bottle` (qty) + `TYPES.spirits` (qty) + `MATERIALS.glass` |
| `beerBottle`   | `ALCOHOL.bottle` (qty) + `TYPES.beer` (qty)    + `MATERIALS.glass` |
| `wineBottle`   | `ALCOHOL.bottle` (qty) + `TYPES.wine` (qty)    + `MATERIALS.glass` |
| `beerCan`      | `ALCOHOL.can` (qty)    + `TYPES.beer` (qty)    + `MATERIALS.aluminium` |
| `waterBottle`  | `SOFTDRINKS.bottle`    + `TYPES.water`         + `MATERIALS.plastic` |
| `fizzyDrinkBottle` | `SOFTDRINKS.bottle` + (no specific type)   + `MATERIALS.plastic` |
| `tinCan`       | `SOFTDRINKS.can`       + `TYPES.soda`          + `MATERIALS.aluminium` |

To recover v4-style per-subtype counts you must combine the object column with the type column, filtered by category section.

**Auto-inferred materials.** During the v5 migration, `ClassifyTagsService::normalizeDeprecatedTag()` attached default materials to subtype-bearing PhotoTags (glass on spirit/beer/wine bottles, aluminium on beer/soda cans, plastic on water/fizzy bottles, etc.). These are intentional (the materials are physically correct for the subtype) and remain on migrated photos. They appear in the `MATERIALS` section of the CSV even if the v4 user never tagged a material.

**`total_tags` = grand total.** The `total_tags` column reads `photos.total_tags`, which is the sum of objects + materials + brands + custom tags (every dimension contributes). Custom-only and brand-only photos therefore have `total_tags > 0`.

## Timeout

Export job timeout: 240 seconds. The `FromQuery` concern chunks automatically (1000 rows per chunk via `config/excel.php`).

## Backlog

**`.xlsx` export with string-typed cells (spreadsheet-safe formula-injection fix).**
The CSV export is intentionally verbatim (see [Cell values are verbatim](#cell-values-are-verbatim--treat-free-text-columns-as-untrusted-in-spreadsheets)),
which leaves users who open exports directly in Excel/Sheets exposed to spreadsheet
formula injection from free-text columns (`custom_tag_*`, `model`, `display_name` — a
tag like `=HYPERLINK(...)` or `=cmd|...`). The correct fix is **not** to mangle the CSV
bytes (that would corrupt the data for programmatic researchers, e.g. negative
longitudes), but to add a parallel **`.xlsx`** export that types those cells as explicit
strings (PhpSpreadsheet `setCellValueExplicit(..., DataType::TYPE_STRING)` / a string
column formatter), so Excel never evaluates them — safe by construction. CSV stays the
verbatim research format; `.xlsx` becomes the recommended download for spreadsheet users.
Not yet scheduled.
