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

**Response:** `{ "success": true }`

Exports only `verified >= ADMIN_APPROVED` photos for the location.

## CSV Column Layout

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
| `total_tags` | `summary.totals.litter` (fallback: `photos.total_tags`) |

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

## Timeout

Export job timeout: 240 seconds. The `FromQuery` concern chunks automatically (1000 rows per chunk via `config/excel.php`).
