<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolSetupSeeder extends Seeder
{
    public function run(): void
    {
        $gradeNames = [
            ['pre_nursery', 'Pre-Nursery'],
            ['nursery_1', 'Nursery 1'],
            ['nursery_2', 'Nursery 2'],
            ['reception', 'Reception'],
            ['primary_1', 'Primary 1'],
            ['primary_2', 'Primary 2'],
            ['primary_3', 'Primary 3'],
            ['primary_4', 'Primary 4'],
            ['primary_5', 'Primary 5'],
        ];

        foreach ($gradeNames as $index => [$key, $name]) {
            $query = DB::table('grade_levels')->where('key', $key);
            $existing = $query->first();
            $values = ['name' => $name, 'sort_order' => $index + 1, 'is_terminal' => $key === 'primary_5', 'is_active' => true, 'updated_at' => now()];
            if ($existing) {
                $query->update($values);
            } else {
                DB::table('grade_levels')->insert($values + ['key' => $key, 'created_at' => now()]);
            }
        }

        foreach (array_keys($gradeNames) as $index => $key) {
            DB::table('grade_levels')->where('key', $key)->update([
                'next_grade_level_id' => $index < count($gradeNames) - 1
                    ? DB::table('grade_levels')->where('key', $gradeNames[$index + 1][0])->value('id')
                    : null,
            ]);
        }

        $subjectNames = [
            'mathematics' => 'Mathematics',
            'english_studies' => 'English Studies',
            'basic_science' => 'Basic Science',
            'crs' => 'CRS',
            'phe' => 'PHE',
            'cca' => 'CCA',
            'social_citizenship_studies' => 'Social & Citizenship Studies',
            'nigerian_history' => 'Nigerian History',
            'hausa' => 'Hausa',
            'right_speech' => 'Right Speech',
            'bst' => 'BST',
            'pvs' => 'PVS',
            'french' => 'French',
        ];

        $subjects = [];
        foreach ($subjectNames as $key => $name) {
            $query = DB::table('subjects')->where('key', $key);
            $existing = $query->first();
            $values = ['name' => $name, 'is_active' => true, 'updated_at' => now()];
            if ($existing) {
                $query->update($values);
            } else {
                DB::table('subjects')->insert($values + ['key' => $key, 'created_at' => now()]);
            }
            $subjects[$key] = DB::table('subjects')->where('key', $key)->value('id');
        }

        $lowerPrimary = ['mathematics', 'english_studies', 'basic_science', 'crs', 'phe', 'cca', 'social_citizenship_studies', 'nigerian_history', 'hausa', 'right_speech'];
        $upperPrimary = ['mathematics', 'english_studies', 'bst', 'crs', 'phe', 'cca', 'social_citizenship_studies', 'nigerian_history', 'pvs', 'french', 'hausa', 'right_speech'];

        foreach (['primary_1', 'primary_2', 'primary_3'] as $gradeKey) {
            $this->setOfferings(DB::table('grade_levels')->where('key', $gradeKey)->value('id'), $lowerPrimary, $subjects);
        }

        foreach (['primary_4', 'primary_5'] as $gradeKey) {
            $this->setOfferings(DB::table('grade_levels')->where('key', $gradeKey)->value('id'), $upperPrimary, $subjects);
        }
    }

    /** @param list<string> $keys @param array<string, int> $subjects */
    private function setOfferings(int $gradeId, array $keys, array $subjects): void
    {
        foreach ($keys as $key) {
            $query = DB::table('class_subjects')
                ->where('grade_level_id', $gradeId)
                ->where('subject_id', $subjects[$key])
                ->whereNull('academic_year_id');
            $existing = $query->first();
            $values = ['is_required' => true, 'updated_at' => now()];
            if ($existing) {
                $query->update($values);
            } else {
                DB::table('class_subjects')->insert($values + [
                    'grade_level_id' => $gradeId,
                    'subject_id' => $subjects[$key],
                    'academic_year_id' => null,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
