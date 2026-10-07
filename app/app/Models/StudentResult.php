<?php

namespace App\Models;

use App\Services\ResultScoring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResult extends Model
{
    protected $fillable = [
        'teacher_submission_id', 'student_profile_id', 'assignment_1', 'assignment_2',
        'assignment_3', 'assignment_4', 'exam_score', 'teacher_comment',
    ];

    protected function casts(): array
    {
        return [
            'assignment_1' => 'decimal:2',
            'assignment_2' => 'decimal:2',
            'assignment_3' => 'decimal:2',
            'assignment_4' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    public function teacherSubmission(): BelongsTo
    {
        return $this->belongsTo(TeacherSubmission::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function applyMarks(array $marks, ResultScoring $scoring): void
    {
        $validated = $scoring->validate($marks);
        foreach (ResultScoring::FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $this->{$field} = $validated[$field];
            }
        }

        $this->total_score = $scoring->total($this->only(ResultScoring::FIELDS));
    }

    public function isComplete(): bool
    {
        foreach (ResultScoring::FIELDS as $field) {
            if ($this->{$field} === null) {
                return false;
            }
        }

        return true;
    }
}
