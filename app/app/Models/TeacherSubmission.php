<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TeacherSubmission extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_REOPENED = 'reopened';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = ['teacher_assignment_id', 'term_id', 'subject_id', 'status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studentResults(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    public function publicationVersions(): HasMany
    {
        return $this->hasMany(PublicationVersion::class)->orderByDesc('version_number');
    }

    public function reopenRequests(): HasMany
    {
        return $this->hasMany(ReopenRequest::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ResultRevision::class);
    }
}
