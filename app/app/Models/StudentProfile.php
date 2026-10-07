<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    protected $fillable = ['person_id', 'student_code', 'admitted_on', 'status', 'left_on', 'completed_on'];

    protected function casts(): array
    {
        return ['admitted_on' => 'date', 'left_on' => 'date', 'completed_on' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function parentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ParentProfile::class, 'parent_student_links')
            ->withPivot(['relationship', 'is_primary_contact', 'valid_from', 'valid_until'])
            ->withTimestamps();
    }

    public function activeParentProfiles(): BelongsToMany
    {
        return $this->parentProfiles()->wherePivotNull('valid_until');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }
}
