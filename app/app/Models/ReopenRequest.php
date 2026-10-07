<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReopenRequest extends Model
{
    protected $fillable = ['teacher_submission_id', 'requested_by', 'reason', 'opened_at', 'resubmitted_at', 'republished_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'resubmitted_at' => 'datetime', 'republished_at' => 'datetime'];
    }

    public function teacherSubmission(): BelongsTo
    {
        return $this->belongsTo(TeacherSubmission::class);
    }
}
