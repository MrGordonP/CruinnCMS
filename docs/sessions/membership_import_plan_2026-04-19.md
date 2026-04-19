# Membership Import — Session Checkpoint
**Date:** 2026-04-19
**Status:** Ready to implement — not yet started

---

## Context

Building a CSV importer for the membership module, based on the IGA 2026 Google Form response export. The importer is fixed-format (not a generic column mapper) because the source is a known Google Form structure.

**Source file used as reference:**
`IGA Membership Form 2026 (Responses) - Form Responses 2.csv`
27 rows, some duplicate emails (Michael Shorten ×2, Stephen Carrington ×2 — keep latest timestamp per email).

---

## Schema Change Required First

Add `member_level` column to `members` table. Create migration:

**File:** `modules/membership/migrations/002_member_level.sql`

```sql
ALTER TABLE members
    ADD COLUMN member_level ENUM('amateur', 'student', 'professional', 'academic') NULL
    AFTER organisation;
```

Also update `MembershipService` and the member form/show templates to expose this field.

---

## CSV Column Mapping

| CSV Header | Maps to |
|---|---|
| `Timestamp` | `members.joined_at` |
| `E-Mail:` | `members.email` *(required, dedup key)* |
| `First Name:` | `members.forenames` |
| `Surname/ Family Name:` | `members.surnames` |
| `County:` | `member_addresses.county` |
| `Membership Type:` | `members.plan_id` *(via plan-mapping UI)* |
| `Is this a renewal or are you a new member:` | `members.status` → `Renewal` = `active`, `New Member` = `applicant` |
| `What level of geologist would you describe yourself as?` | `members.member_level` → normalised to enum |
| `How will you be paying your Membership Fee?` | `membership_subscriptions.notes` |
| `What reference did you use / what is the transaction number?` | `membership_subscriptions.payment_reference` |
| `What date did you make the transfer on?` | `membership_subscriptions.paid_at` |

Columns not imported: interest/event preference columns, suggestion box, GDPR consent checkboxes.

**Geologist level normalisation:**
- `Amateur` → `amateur`
- `Student` → `student`
- `Professional` → `professional`
- `Academic` → `academic`
- Anything else → `NULL`

---

## Import Flow

1. `GET /admin/membership/import`
   Upload form. User selects CSV file, sets duplicate handling (Skip / Update), optionally sets a default status override.

2. `POST /admin/membership/import/preview`
   - Parse CSV
   - Deduplicate within file (keep latest timestamp per email)
   - Detect distinct "Membership Type" strings in the file
   - Show plan-mapping form: each unique membership type string → dropdown of existing `membership_plans`
   - Show preview table of rows with highlights for: missing email, duplicate in DB, invalid date

3. `POST /admin/membership/import/commit`
   - Re-parse CSV (re-uploaded via hidden session or re-POST)
   - Apply plan mapping
   - Write `members` + `member_addresses` (county only) + `membership_subscriptions` per row
   - Return results summary: imported / updated / skipped / errors

---

## Files to Create / Modify

### New files
- `modules/membership/migrations/002_member_level.sql` — ALTER TABLE
- `modules/membership/src/Services/MembershipImportService.php` — parse, validate, write
- `modules/membership/templates/admin/membership/members/import.php` — upload + preview + commit UI

### Modified files
- `modules/membership/module.php` — add 3 routes before existing `{id}` wildcards
- `modules/membership/src/Controllers/MembershipAdminController.php` — add `importForm()`, `importPreview()`, `importCommit()` methods
- `modules/membership/src/Services/MembershipService.php` — add `member_level` to `createMember()`, `updateMember()` field sets
- `modules/membership/templates/admin/membership/members/form.php` — add member_level field
- `modules/membership/templates/admin/membership/members/show.php` — display member_level

---

## Routes to Add (in module.php, before `{id}` wildcards)

```php
['GET',  '/admin/membership/import',         'MembershipAdminController', 'importForm'],
['POST', '/admin/membership/import/preview', 'MembershipAdminController', 'importPreview'],
['POST', '/admin/membership/import/commit',  'MembershipAdminController', 'importCommit'],
```

---

## MembershipImportService outline

```php
class MembershipImportService
{
    // Parse uploaded CSV → array of normalised rows
    public function parseCsv(string $filePath): array {}

    // Deduplicate within parsed rows (latest timestamp wins per email)
    public function deduplicateRows(array $rows): array {}

    // Return distinct membership type strings found in rows
    public function distinctMembershipTypes(array $rows): array {}

    // Validate a single row, return array of error strings (empty = valid)
    public function validateRow(array $row): array {}

    // Commit rows to DB. $planMap = ['Individual - €20' => 3, ...]
    // $options = ['on_duplicate' => 'skip'|'update']
    // Returns ['imported'=>n, 'updated'=>n, 'skipped'=>n, 'errors'=>[]]
    public function commitRows(array $rows, array $planMap, array $options): array {}
}
```

---

## Preview table session strategy

PHP `$_SESSION` to carry the parsed rows between preview and commit (avoids re-upload). Key: `membership_import_rows`. Clear after commit or on fresh upload.

---

## Notes / Edge Cases

- `mphilcox` — malformed email in the real data (no domain). Flag as error in preview.
- Dates in CSV are `M/D/YYYY` format — parse with `DateTime::createFromFormat('m/d/Y', ...)` and fall back to `n/j/Y`.
- Some `County` values have trailing spaces (e.g. `"Dublin "`) — trim on import.
- Payment method `Cash/ Cheque` has no reference/date — `payment_reference` and `paid_at` will be NULL; subscription status should be `pending` not `paid`.
- `Bank Transfer` rows with a reference and date → subscription status `paid`.
