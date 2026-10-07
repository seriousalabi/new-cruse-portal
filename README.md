# New Cruse Academy custom portal

Separate Laravel implementation workspace. It does not modify the design handoff or openSIS pilot. Nothing here is deployed or published.

## Current state

- Laravel 13 is scaffolded in `app/`; its dependencies were installed by Composer in the user's PowerShell.
- Official Primary 1–5 subjects are documented in `docs/official-curriculum.md` and are loaded by the school-setup seeder.
- Initial migrations define access roles, official class/subject offerings, school years/terms, student/guardian/staff records, assignments, results, publication versions, corrections, promotion decisions and audit events. Workflow enforcement and screens remain in progress.
- The first role-protected admin workflow now configures an academic year and exactly three non-overlapping term windows. It supports draft setup, current-year activation and audit events; it uses the previously assumed dates as editable form defaults.
- The Admin/Super Admin student roster supports individual student creation, current-year class enrollment, search/filtering and CSV preview/commit. CSV import validates the school template, IDs, dates and configured classes; rejected rows block the batch, committed files cannot be re-imported unchanged, and uncommitted preview payloads are purged on a roster visit after 24 hours.
- The Admin/Super Admin family directory supports parent/guardian contact profiles, linking guardians to current-year students (including siblings), primary-contact designation and ending links while retaining history. These records do not yet create parent login accounts.
- The Admin/Super Admin staff area supports teacher contact profiles, one current-year class assignment per teacher, multiple offered subjects, assignment edits before any result submission, and ending assignments/staff profiles without deleting history. Ending a profile also disables a linked login if one exists; teacher invitations are not implemented yet.
- Docker Compose files remain available for a later runtime switch. Local development uses PHP and SQLite by default unless a local MariaDB/MySQL service is selected.

## Local startup without Docker

1. Open PowerShell in `C:\Users\USER\Documents\Codex\2026-10-01\an\work\new-cruse-portal\app`.
2. Run `php artisan migrate --seed` to create the database tables and seed roles, permissions, the class ladder and official primary subjects.
3. Run `php artisan portal:create-super-admin` to create the first administrator interactively. No default password is stored in the project.
4. Run `php artisan serve` to start the local Laravel development server. The default URL is `http://127.0.0.1:8000`.
5. Use only synthetic student data. Do not deploy, expose a public tunnel, or import the real student roster at this stage. Docker scripts and Compose files are retained for a later switch if wanted.

After later local build changes, run `php artisan migrate` from `app/` before refreshing the portal. This applies new migrations without resetting the local database.

## Current local workflow

After signing in as Admin or Super Admin, open **School setup** from the dashboard. Create and review the academic year and its terms, then activate the intended current year. These setup records live only in the portal's local database.

Open **Students and families** from the dashboard to add a student or preview a class-roster CSV. The school template is available on that page. Import commits create profile and enrollment records only.

From the roster, open **Manage parent/guardian profiles and student links** to create guardian contacts and link children. A later account-invitation workflow will connect verified parent contacts to sign-in accounts.

Open **Staff and teaching** from the dashboard to create teacher profiles and assign each one class plus any subjects offered in that class. The current primary curriculum is available for assignments; nursery/reception may be assigned before their subject lists are finalized. Teacher sign-in invitations remain separate.

