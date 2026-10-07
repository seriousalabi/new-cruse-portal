<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentProfile extends Model
{
    protected $fillable = ['person_id', 'email', 'status'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'parent_student_links')
            ->withPivot(['relationship', 'is_primary_contact', 'valid_from', 'valid_until'])
            ->withTimestamps();
    }

    public function activeStudents(): BelongsToMany
    {
        return $this->students()->wherePivotNull('valid_until');
    }
}
