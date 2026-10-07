<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationVersion extends Model
{
    protected $fillable = ['teacher_submission_id', 'version_number', 'published_by', 'published_at', 'incomplete_override', 'override_reason', 'result_snapshot'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'incomplete_override' => 'boolean', 'result_snapshot' => 'array'];
    }

    public function teacherSubmission(): BelongsTo
    {
        return $this->belongsTo(TeacherSubmission::class);
    }
}
