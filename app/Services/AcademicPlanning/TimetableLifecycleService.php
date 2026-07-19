<?php

namespace App\Services\AcademicPlanning;

use App\Models\WeeklyTimetable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableLifecycleService
{
    public function publish(WeeklyTimetable $timetable, int $userId): WeeklyTimetable
    {
        if (! $timetable->is_generated || ! $timetable->entries()->active()->exists()) {
            throw ValidationException::withMessages([
                'timetable' => 'Only a generated timetable containing active entries can be published.',
            ]);
        }

        return DB::transaction(function () use ($timetable, $userId): WeeklyTimetable {
            WeeklyTimetable::query()
                ->forSubscription((int) $timetable->subscription_id)
                ->published()
                ->forClass(
                    (int) $timetable->grade_id,
                    $timetable->section_id,
                    $timetable->stream_id
                )
                ->when(
                    $timetable->academic_year_id !== null,
                    fn ($query) => $query->where('academic_year_id', $timetable->academic_year_id)
                )
                ->where($timetable->getQualifiedKeyName(), '!=', $timetable->getKey())
                ->update([
                    'status' => WeeklyTimetable::STATUS_ARCHIVED,
                    'is_active' => false,
                    'archived_at' => now(),
                ]);

            $timetable->forceFill([
                'status' => WeeklyTimetable::STATUS_PUBLISHED,
                'is_active' => true,
                'published_at' => now(),
                'published_by' => $userId,
                'archived_at' => null,
            ])->save();

            return $this->fresh($timetable);
        });
    }

    public function archive(WeeklyTimetable $timetable): WeeklyTimetable
    {
        $timetable->forceFill([
            'status' => WeeklyTimetable::STATUS_ARCHIVED,
            'is_active' => false,
            'archived_at' => now(),
        ])->save();

        return $this->fresh($timetable);
    }

    public function restoreToDraft(WeeklyTimetable $timetable): WeeklyTimetable
    {
        $timetable->forceFill([
            'status' => WeeklyTimetable::STATUS_DRAFT,
            'is_active' => true,
            'published_at' => null,
            'published_by' => null,
            'archived_at' => null,
        ])->save();

        return $this->fresh($timetable);
    }

    public function createVersion(WeeklyTimetable $source, ?string $name = null): WeeklyTimetable
    {
        return DB::transaction(function () use ($source, $name): WeeklyTimetable {
            $nextVersion = (int) WeeklyTimetable::query()
                ->forSubscription((int) $source->subscription_id)
                ->forClass((int) $source->grade_id, $source->section_id, $source->stream_id)
                ->when(
                    $source->academic_year_id !== null,
                    fn ($query) => $query->where('academic_year_id', $source->academic_year_id)
                )
                ->max('version') + 1;

            $copy = $source->replicate([
                'status',
                'version',
                'published_at',
                'published_by',
                'archived_at',
                'created_at',
                'updated_at',
            ]);

            $copy->forceFill([
                'name' => $name ?: $source->name . ' v' . $nextVersion,
                'status' => WeeklyTimetable::STATUS_DRAFT,
                'version' => $nextVersion,
                'published_at' => null,
                'published_by' => null,
                'archived_at' => null,
                'is_active' => true,
            ])->save();

            foreach ($source->entries()->get() as $entry) {
                $copy->entries()->create($entry->replicate([
                    'weekly_timetable_id',
                    'created_at',
                    'updated_at',
                ])->toArray());
            }

            return $this->fresh($copy);
        });
    }

    private function fresh(WeeklyTimetable $timetable): WeeklyTimetable
    {
        return $timetable->fresh([
            'template',
            'academicYear',
            'grade',
            'section',
            'stream',
            'publisher:id,name,email',
        ])->loadCount('entries');
    }
}
