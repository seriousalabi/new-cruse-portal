<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

final class ResultScoring
{
    public const FIELDS = ['assignment_1', 'assignment_2', 'assignment_3', 'assignment_4', 'exam_score'];

    private const MAXIMUMS = [
        'assignment_1' => 10,
        'assignment_2' => 10,
        'assignment_3' => 10,
        'assignment_4' => 10,
        'exam_score' => 60,
    ];

    /** @return array<string, mixed> */
    public function validate(array $marks): array
    {
        $rules = [];
        foreach (self::MAXIMUMS as $field => $maximum) {
            $rules[$field] = ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maximum];
        }

        return Validator::make($marks, $rules)->validate();
    }

    /** Return null while any component is missing; otherwise return the exact two-place total. */
    public function total(array $marks): ?string
    {
        $totalCents = 0;
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $marks) || $marks[$field] === null || $marks[$field] === '') {
                return null;
            }

            $normalized = number_format((float) $marks[$field], 2, '.', '');
            [$whole, $fraction] = explode('.', $normalized, 2);
            $totalCents += ((int) $whole * 100) + (int) $fraction;
        }

        return number_format($totalCents / 100, 2, '.', '');
    }

    public function isFailing(?string $total): ?bool
    {
        if ($total === null) {
            return null;
        }

        return (int) round((float) $total * 100) <= 4000;
    }

    public function reachesNonPromotionThreshold(int $failedSubjects, int $requiredSubjects): ?bool
    {
        if ($requiredSubjects <= 0) {
            return null;
        }

        return $failedSubjects * 2 >= $requiredSubjects;
    }
}
