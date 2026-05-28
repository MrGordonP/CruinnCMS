# 2026-05-28 CHECKPOINT — Membership Table-Level Decomposition

Date: 2026-05-28
Branch: main
Status: checkpoint committed for testing

## Scope

This checkpoint captures the next decomposition step for membership-owned profile fragments.

The goal of this slice was to stop bundling member-table and address-table data into one fragment and begin splitting the membership profile surface to table level.

## Why

The previous membership fragment split still conflated data from different tables:

- member identity/details came from the `members` table
- address fields came from `member_addresses`

That made the fragment boundary too coarse. Address needed to be its own block instead of living inside the combined member details form.

## Changes

### 1. Added a dedicated membership address provider

A new module content provider was registered for address data so address can be composed independently from member-record details.

Files:

- `CruinnCMS/modules/membership/module.php`
- `CruinnCMS/modules/membership/src/Controllers/MembershipContentController.php`

### 2. Reduced member details to the members table surface

The existing member details fragment was narrowed so it now covers only the member-record fields:

- name
- member ID
- email
- institute / organisation
- public directory / show-name flag

Address fields were removed from this fragment.

File:

- `CruinnCMS/modules/membership/templates/public/membership/module-content/member-details-form.php`

### 3. Added a separate address fragment

A new address fragment was added for the `member_addresses` surface:

- address line 1
- address line 2
- county
- country
- eircode / postcode
- phone

File:

- `CruinnCMS/modules/membership/templates/public/membership/module-content/member-address-form.php`

### 4. Added migration support for existing profile compositions

A follow-up migration was added so existing profile system-page compositions that already use `membership:member-details-form` gain the new address fragment without losing the address section.

File:

- `CruinnCMS/migrations/core/025_membership_address_fragment.sql`

## Validation

- PHP lint passed for the updated/new membership template files.
- Diagnostics reported no errors in the touched PHP and SQL files.

## Remaining Follow-up

The mixed `member-admin-stats` fragment remains unresolved and still crosses module/core concerns. That needs a later slice to decompose admin/core-owned elements away from membership-owned fragments.
