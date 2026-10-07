<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GradeLevel extends Model
{
    protected $fillable = ['key', 'name', 'sort_order', 'next_grade_level_id', 'is_terminal', 'is_active'];

    protected function casts(): array
    {
        return ['is_terminal' => 'boolean', 'is_active' => 'boolean'];
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects')
            ->withPivot(['academic_year_id', 'is_required'])
            ->withTimestamps();
    }
}
