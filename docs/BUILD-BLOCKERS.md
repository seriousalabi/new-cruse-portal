# Build status and decisions to confirm

## Build environment

Laravel 13 and its Composer dependencies have been created in `app/` using the user's PHP 8.5/Composer installation. Development will proceed without Docker for now. The exact local database service and hosting target remain to be confirmed before they are needed.

## Agreed behavior to implement

- Teacher enters and submits marks; Admin verifies each submission. Admin approval is the publish event and makes it parent-visible.
- Mark formula: four assignment scores (0–10 each) plus exam (0–60); total out of 100. Decimal scores are supported up to two decimal places.
- Fail is 0.00–40.00 inclusive. At least half of a student's required subjects failing flags them for non-promotion.
- N/A is a missing-result state, never zero; N/A blocks automatic pass/fail classification until resolved.
- Admin and Super Admin can approve an incomplete publication override; parents see N/A for missing marks.
- Published submissions lock. Only Super Admin can reopen for correction, recording reason and before/after values. Corrected submission returns to Admin verification.
- Admin cannot delete accounts. Super Admin has account-delete permission. Students leaving school are marked Left/archived to preserve history. Teacher replacement preserves prior identity/history.
- Teacher profiles and class assignments are maintained separately from login accounts. A teacher may have only one class assignment per academic year and several subjects. Ending a staff profile closes active assignments, disables a linked login if present, and retains records.
- Promotion list is generated automatically, reviewed by Admin or Super Admin, and applied after confirmation.

## Remaining policy or clarification items

1. Current interpretation: teacher’s quoted “publish” means Submit; Admin approval publishes the official parent-visible result. Confirm only if this interpretation is wrong.
2. Confirmed: Super Admin reopens one whole teacher/class/subject/term submission; corrections are captured at individual student-row level.
3. Confirmed: parents keep seeing the last approved version until corrected results are republished.
4. Confirmed: N/A results remain pending for promotion and are not treated as pass/fail. Still define subject exemptions.
5. Confirm transfer/repeater handling and exit record fields. Primary 5 completers are marked Completed with history retained; there is no Primary 6.
6. Official Primary 1–5 subjects are recorded in `docs/official-curriculum.md`. Confirm nursery/Reception subjects and exact term dates/year labels when the school sends them.
7. Confirm the student data source/export, stable IDs, historical results to import, required fields and who approves reconciliation.
8. Confirm teacher/parent invitation expiry, recovery and no-email account process.
9. Confirm permanent deletion/retention rules, audit retention, and hosting region/data-location requirements before live data or production hosting.

## Approval boundary

This workspace is not deployed or published. Do not deploy, publish, import real student data, or make a change to the original openSIS installation without the user's explicit confirmation.


