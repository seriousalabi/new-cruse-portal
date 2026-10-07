# Student and staff data import plan

## Fastest safe path

Use a school-approved Excel workbook as the collection source, with one sheet per template below, then export each sheet to UTF-8 CSV for the first import. This separates students, parents and relationships so siblings, multiple guardians, and shared parent accounts do not produce duplicate people. Once the portal exists, provide an import screen that accepts those same CSVs and returns a preview and row-level error report.

Blank starter templates:

- `../import-templates/students.csv`
- `../import-templates/parents.csv`
- `../import-templates/parent_student_links.csv`
- `../import-templates/teachers.csv`
- `../import-templates/teacher_assignments.csv`

## Import order

1. Configure academic year, classes and official class/subject offerings. Primary 1–5 offerings are defined in `official-curriculum.md`; nursery and Reception offerings remain pending.
2. Clean and agree a stable student ID for every student. IDs must not be recycled.
3. Import parents/guardians and staff, deduplicating by an agreed stable external ID; email alone may not uniquely identify a person.
4. Import student profiles and the current-year class enrolment.
5. Import parent–student links, then teacher–class–subject assignments.
6. Review the preview and exception report with the school before committing the batch.
7. Send invitations only after account contact details and role assignments are reviewed.

## Validation rules for the importer

- Required IDs and names are present; IDs are unique in each file and match across linked files.
- Dates use `YYYY-MM-DD`; invalid dates are rejected, not guessed.
- Class and subject codes must already exist in school setup.
- Statuses and relationship values must be selected from a published allowed-value list.
- Duplicate external IDs are rejected or reported for explicit update; the importer never guesses which row wins.
- Every row is previewed as create/update/reject, with specific errors. The user downloads an error CSV, corrects it, and retries.
- Import runs have a batch ID, uploader, time, file checksum, result counts and audit event. Re-importing the same batch must not silently create duplicate students or links.
- The system should offer a dry-run preview first, then an explicit commit. Keep a batch reversal/undo strategy for records created by that batch, subject to later use and audit retention.

## Suggested first migration

Do not type the full roster manually. Ask the school to export its current student register and guardian list from the source it currently trusts. Map its columns to the templates, normalize names/phone numbers/class labels, resolve duplicate IDs and missing guardian links, then test with one class before importing the remaining classes. Keep source row number in the import report for reconciliation. Do not import old marks until the school confirms which academic years and historical results are required.

## Student data protection

Use synthetic data during development and design review. The school should control the source workbook and approve the final roster mapping. Do not place student/guardian files in public repositories, shared screenshots or test fixtures. Restrict import permission to Super Admin/Admin as decided, and audit who imports or changes a batch. Choose hosting/data location and access controls before uploading real student records.

## Items requiring school input

- Current source of truth and export format (openSIS, Excel, paper register, or a mix).
- Which historical school years and marks must be brought across.
- Stable ID convention if students currently have no reliable ID.
- Required/optional student and parent fields, especially date of birth and guardian email.
- Whether a parent without email can use the portal and what verified contact method invitations use.
- Duplicate resolution authority and who signs off on import reconciliation.


## Confirmed class boundary

The configured grade ladder stops at Primary 5; no Primary 6 class is to be created. Eligible students completing Primary 5 are marked Completed at rollover, with their history retained.

## Current importer implementation

The local portal currently supports student CSV preview and commit for up to 2,000 rows per upload. It accepts the provided student template, recognizes class keys and short codes (PN, N1, N2, R1, P1–P5), rejects duplicate/existing IDs and invalid dates/statuses, and assigns accepted rows to the active academic year. A batch with any rejected row cannot be committed; a rejected-row CSV report is available for reconciliation. Imported student and enrollment records are audited. Preview data is deleted when the batch is discarded, cleared after commit, or removed after 24 hours. Parent/guardian profiles and student links can be maintained individually in the Admin/Super Admin family directory. Parent CSV import, update matching, login invitations and import reversal workflows are not implemented yet.

