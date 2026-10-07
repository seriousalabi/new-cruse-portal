# V1 relational model and current migrations

The initial Laravel migrations implement this schema in `app/database/migrations/`. School configuration is record-based so authorized users can maintain classes, terms, subjects and yearly offerings without code changes.

```mermaid
erDiagram
  PEOPLE ||--o| USERS : login
  PEOPLE ||--o| STUDENT_PROFILES : student
  PEOPLE ||--o| PARENT_PROFILES : guardian
  PEOPLE ||--o| STAFF_PROFILES : staff
  USERS ||--o{ USER_ROLES : assigned
  ROLES ||--o{ USER_ROLES : grants
  ROLES ||--o{ ROLE_PERMISSIONS : permits
  PERMISSIONS ||--o{ ROLE_PERMISSIONS : includes
  ACADEMIC_YEARS ||--o{ TERMS : includes
  ACADEMIC_YEARS ||--o{ CLASS_SUBJECTS : version
  GRADE_LEVELS ||--o{ CLASS_SUBJECTS : offers
  SUBJECTS ||--o{ CLASS_SUBJECTS : offered
  GRADE_LEVELS ||--o{ GRADE_LEVELS : next_grade
  STUDENT_PROFILES ||--o{ PARENT_STUDENT_LINKS : linked
  PARENT_PROFILES ||--o{ PARENT_STUDENT_LINKS : guardian
  STUDENT_PROFILES ||--o{ STUDENT_ENROLLMENTS : enrols
  ACADEMIC_YEARS ||--o{ STUDENT_ENROLLMENTS : year
  GRADE_LEVELS ||--o{ STUDENT_ENROLLMENTS : class
  STAFF_PROFILES ||--o{ TEACHER_ASSIGNMENTS : assigned
  ACADEMIC_YEARS ||--o{ TEACHER_ASSIGNMENTS : year
  GRADE_LEVELS ||--o{ TEACHER_ASSIGNMENTS : class
  TEACHER_ASSIGNMENTS ||--o{ TEACHER_ASSIGNMENT_SUBJECTS : teaches
  SUBJECTS ||--o{ TEACHER_ASSIGNMENT_SUBJECTS : subject
  TEACHER_ASSIGNMENTS ||--o{ TEACHER_SUBMISSIONS : scope
  TERMS ||--o{ TEACHER_SUBMISSIONS : term
  SUBJECTS ||--o{ TEACHER_SUBMISSIONS : subject
  TEACHER_SUBMISSIONS ||--o{ STUDENT_RESULTS : contains
  STUDENT_PROFILES ||--o{ STUDENT_RESULTS : receives
  TEACHER_SUBMISSIONS ||--o{ PUBLICATION_VERSIONS : published
  TEACHER_SUBMISSIONS ||--o{ REOPEN_REQUESTS : reopened
  REOPEN_REQUESTS ||--o{ RESULT_REVISIONS : explains
  STUDENT_RESULTS ||--o{ RESULT_REVISIONS : changed
  PARENT_PROFILES ||--o{ CORRECTION_REQUESTS : raises
  STUDENT_PROFILES ||--o{ CORRECTION_REQUESTS : concerns
  TERMS ||--o{ CORRECTION_REQUESTS : term
  ACADEMIC_YEARS ||--o{ PROMOTION_RUNS : reviews
  PROMOTION_RUNS ||--o{ PROMOTION_DECISIONS : proposes
  STUDENT_PROFILES ||--o{ PROMOTION_DECISIONS : evaluated
  USERS ||--o{ AUDIT_EVENTS : acts
```

## Agreed rules represented in the schema

- One teacher submission is one teacher assignment, subject and term. The assignment ties the teacher to one class in an academic year; subjects are many-to-many within that class.
- A student has one enrollment per academic year. Their class history is retained across years. Parent links are distinct records to support siblings and multiple guardians.
- Four assignment marks and an exam mark are nullable decimals with scale 2. Application validation enforces assignment bounds 0–10 and exam bounds 0–60. The server calculates total out of 100.00 only when all five marks exist; missing remains N/A, not zero.
- A total from 0.00 to 40.00 inclusive is failing. At least half of required subjects failing flags non-promotion. An unresolved N/A result leaves promotion pending. Admin or Super Admin must review and confirm a promotion run.
- Primary 5 is terminal. Eligible completers are marked Completed and retain history; there is no Primary 6.
- Admin approval creates an immutable publication version and makes it parent-visible. An incomplete-result override records its reason. Reopening requires Super Admin and a reason; row-level before/after values are stored in result revisions. Parents keep seeing the last approved version until a correction is republished.
- Users have four role grants: Super Admin, Admin, Teacher, Parent. Role permissions are seeded explicitly. Admin cannot delete login accounts; the Super Admin permission is separate. User accounts use soft deletion so historical records continue to resolve.
- Student and staff external codes are stable import keys; names and email addresses are not matching keys.

## Confirmed scope still to configure

- Primary 1–3 use the ten subjects and Primary 4–5 use the twelve subjects in `official-curriculum.md`. Nursery/Reception subjects are pending.
- Exact school-year labels and term dates are pending; the tables support configuring them without assuming dates.
- Subject exemptions, transfer/repeater handling, invitations and recovery, permanent erasure/retention, and hosting/data-location policy remain open before those affected features are finalized.

