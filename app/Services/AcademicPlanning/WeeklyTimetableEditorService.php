<?php

namespace App\Services\AcademicPlanning;

use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetableRoom;
use App\Models\WeeklyTimetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyTimetableEditorService
{
    public function grid(WeeklyTimetable $timetable): array
    {
        $entries = $timetable->entries()
            ->with($this->entryRelations())
            ->orderBy('weekday')
            ->orderBy('school_bell_id')
            ->orderBy('parallel_group_id')
            ->orderBy('id')
            ->get();

        return [
            'timetable' => $timetable->load([
                'template', 'academicYear', 'grade', 'section', 'stream',
            ])->loadCount(['entries', 'activeEntries']),
            'entries' => $entries,
            'grid' => $entries
                ->groupBy(fn (TimetableEntry $entry) => (int) $entry->weekday)
                ->map(fn ($dayEntries) => $dayEntries
                    ->groupBy(fn (TimetableEntry $entry) => (int) $entry->school_bell_id)
                    ->map(fn ($slotEntries) => $slotEntries->values())
                ),
        ];
    }

    public function create(WeeklyTimetable $timetable, array $data): TimetableEntry
    {
        $this->assertEditable($timetable);
        $this->assertResourceContext($timetable, $data);
        $this->assertNoConflicts($timetable, $data);

        $entry = $timetable->entries()->create($this->normalizedPayload($data));
        $this->markDraft($timetable);

        return $entry->load($this->entryRelations());
    }

    public function update(TimetableEntry $entry, array $data): TimetableEntry
    {
        $timetable = $entry->weeklyTimetable;
        $this->assertEditable($timetable);

        if ($entry->is_locked && ! ($data['allow_locked_update'] ?? false)) {
            throw ValidationException::withMessages([
                'entry' => 'This timetable entry is locked. Unlock it before editing.',
            ]);
        }

        $payload = array_merge($entry->only([
            'weekday', 'school_bell_id', 'teacher_id', 'subject_id', 'lesson_id',
            'parallel_group_id', 'student_group_name', 'room_id', 'room_no',
            'is_parallel', 'is_substitution', 'substitute_teacher_id', 'remarks',
            'is_locked', 'is_active',
        ]), Arr::except($data, ['allow_locked_update']));

        $this->assertResourceContext($timetable, $payload);
        $this->assertNoConflicts($timetable, $payload, $entry->id);
        $entry->fill($this->normalizedPayload($payload))->save();
        $this->markDraft($timetable);

        return $entry->fresh($this->entryRelations());
    }

    public function delete(TimetableEntry $entry, bool $forceLocked = false): void
    {
        $timetable = $entry->weeklyTimetable;
        $this->assertEditable($timetable);

        if ($entry->is_locked && ! $forceLocked) {
            throw ValidationException::withMessages([
                'entry' => 'This timetable entry is locked and cannot be deleted.',
            ]);
        }

        $entry->delete();
        $this->markDraft($timetable);
    }

    public function replaceGrid(
        WeeklyTimetable $timetable,
        array $entries,
        bool $preserveLocked = true
    ): array {
        $this->assertEditable($timetable);

        return DB::transaction(function () use ($timetable, $entries, $preserveLocked): array {
            $lockedEntries = $preserveLocked
                ? $timetable->entries()->locked()->get()->keyBy('id')
                : collect();

            foreach ($entries as $index => $payload) {
                if (! isset($payload['id'])) {
                    continue;
                }

                $existing = $timetable->entries()->whereKey((int) $payload['id'])->first();
                if (! $existing) {
                    throw ValidationException::withMessages([
                        "entries.{$index}.id" => 'The timetable entry does not belong to this timetable.',
                    ]);
                }
            }

            $deleteQuery = $timetable->entries();
            if ($preserveLocked) {
                $deleteQuery->unlocked();
            }
            $deleteQuery->delete();

            foreach (array_values($entries) as $payload) {
                $entryId = isset($payload['id']) ? (int) $payload['id'] : null;

                if ($preserveLocked && $entryId && $lockedEntries->has($entryId)) {
                    continue;
                }

                $payload = Arr::except($payload, ['id']);
                $this->assertResourceContext($timetable, $payload);
                $this->assertNoConflicts($timetable, $payload);
                $timetable->entries()->create($this->normalizedPayload($payload));
            }

            $this->markDraft($timetable);

            return $this->grid($timetable->fresh());
        });
    }

    private function assertEditable(WeeklyTimetable $timetable): void
    {
        if ($timetable->isArchived()) {
            throw ValidationException::withMessages([
                'timetable' => 'An archived timetable cannot be edited. Restore it to draft first.',
            ]);
        }
    }

    private function assertResourceContext(WeeklyTimetable $timetable, array $data): void
    {
        if (! empty($data['subject_id'])) {
            $subject = Subject::query()
                ->where('subscription_id', $timetable->subscription_id)
                ->whereKey((int) $data['subject_id'])
                ->first();

            if (! $subject || (int) $subject->grade_id !== (int) $timetable->grade_id) {
                throw ValidationException::withMessages([
                    'subject_id' => 'The selected subject does not belong to this timetable grade.',
                ]);
            }
        }

        if (! empty($data['lesson_id'])) {
            $lesson = Lesson::query()
                ->where('subscription_id', $timetable->subscription_id)
                ->whereKey((int) $data['lesson_id'])
                ->first();

            if (! $lesson
                || (int) $lesson->grade_id !== (int) $timetable->grade_id
                || (! empty($data['subject_id'])
                    && (int) $lesson->subject_id !== (int) $data['subject_id'])) {
                throw ValidationException::withMessages([
                    'lesson_id' => 'The selected lesson does not match this timetable grade and subject.',
                ]);
            }
        }

        if (! empty($data['room_id'])) {
            $room = TimetableRoom::query()
                ->where('subscription_id', $timetable->subscription_id)
                ->whereKey((int) $data['room_id'])
                ->first();

            if (! $room || ! $room->supportsSubject(
                ! empty($data['subject_id']) ? (int) $data['subject_id'] : null
            )) {
                throw ValidationException::withMessages([
                    'room_id' => 'The selected room does not support this subject.',
                ]);
            }
        }
    }

    private function assertNoConflicts(
        WeeklyTimetable $timetable,
        array $data,
        ?int $excludedEntryId = null
    ): void {
        $weekday = (int) $data['weekday'];
        $bellId = (int) $data['school_bell_id'];
        $parallelGroupId = isset($data['parallel_group_id'])
            ? (int) $data['parallel_group_id']
            : null;
        $isParallel = (bool) ($data['is_parallel'] ?? false);

        $classConflict = $timetable->entries()
            ->active()
            ->forSlot($weekday, $bellId)
            ->when($excludedEntryId, fn (Builder $query) => $query->where('id', '!=', $excludedEntryId))
            ->when(
                $isParallel && $parallelGroupId,
                fn (Builder $query) => $query->where(function (Builder $scope) use ($parallelGroupId) {
                    $scope->where('is_parallel', false)
                        ->orWhereNull('parallel_group_id')
                        ->orWhere('parallel_group_id', '!=', $parallelGroupId);
                })
            )
            ->exists();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'school_bell_id' => 'This class already has another entry in the selected period.',
            ]);
        }

        $teacherId = ! empty($data['is_substitution']) && ! empty($data['substitute_teacher_id'])
            ? (int) $data['substitute_teacher_id']
            : (! empty($data['teacher_id']) ? (int) $data['teacher_id'] : null);

        if ($teacherId !== null) {
            $teacherConflict = TimetableEntry::query()
                ->active()
                ->forSubscription((int) $timetable->subscription_id)
                ->forSlot($weekday, $bellId)
                ->whereHas('weeklyTimetable', function (Builder $query) use ($timetable) {
                    $query->when(
                        $timetable->academic_year_id !== null,
                        fn (Builder $scope) => $scope->where('academic_year_id', $timetable->academic_year_id)
                    );
                })
                ->where(function (Builder $query) use ($teacherId) {
                    $query->where(function (Builder $scope) use ($teacherId) {
                        $scope->where('is_substitution', false)
                            ->where('teacher_id', $teacherId);
                    })->orWhere(function (Builder $scope) use ($teacherId) {
                        $scope->where('is_substitution', true)
                            ->where('substitute_teacher_id', $teacherId);
                    });
                })
                ->when($excludedEntryId, fn (Builder $query) => $query->where('id', '!=', $excludedEntryId))
                ->exists();

            if ($teacherConflict) {
                throw ValidationException::withMessages([
                    'teacher_id' => 'The selected teacher is already assigned in this period.',
                ]);
            }
        }

        if (! empty($data['room_id'])) {
            $roomConflict = TimetableEntry::query()
                ->active()
                ->forSubscription((int) $timetable->subscription_id)
                ->forSlot($weekday, $bellId)
                ->forRoom((int) $data['room_id'])
                ->whereHas('weeklyTimetable', function (Builder $query) use ($timetable) {
                    $query->when(
                        $timetable->academic_year_id !== null,
                        fn (Builder $scope) => $scope->where('academic_year_id', $timetable->academic_year_id)
                    );
                })
                ->when($excludedEntryId, fn (Builder $query) => $query->where('id', '!=', $excludedEntryId))
                ->exists();

            if ($roomConflict) {
                throw ValidationException::withMessages([
                    'room_id' => 'The selected room is already occupied in this period.',
                ]);
            }
        }
    }

    private function markDraft(WeeklyTimetable $timetable): void
    {
        $timetable->forceFill([
            'status' => WeeklyTimetable::STATUS_DRAFT,
            'is_active' => true,
            'published_at' => null,
            'published_by' => null,
            'archived_at' => null,
        ])->save();
    }

    private function normalizedPayload(array $data): array
    {
        return [
            'weekday' => (int) $data['weekday'],
            'school_bell_id' => (int) $data['school_bell_id'],
            'teacher_id' => isset($data['teacher_id']) ? (int) $data['teacher_id'] : null,
            'subject_id' => isset($data['subject_id']) ? (int) $data['subject_id'] : null,
            'lesson_id' => isset($data['lesson_id']) ? (int) $data['lesson_id'] : null,
            'parallel_group_id' => isset($data['parallel_group_id']) ? (int) $data['parallel_group_id'] : null,
            'student_group_name' => $data['student_group_name'] ?? null,
            'room_id' => isset($data['room_id']) ? (int) $data['room_id'] : null,
            'room_no' => $data['room_no'] ?? null,
            'is_parallel' => (bool) ($data['is_parallel'] ?? false),
            'is_substitution' => (bool) ($data['is_substitution'] ?? false),
            'substitute_teacher_id' => isset($data['substitute_teacher_id'])
                ? (int) $data['substitute_teacher_id']
                : null,
            'remarks' => $data['remarks'] ?? null,
            'is_locked' => (bool) ($data['is_locked'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function entryRelations(): array
    {
        return [
            'bell', 'teacher:id,name,email', 'substituteTeacher:id,name,email',
            'subject:id,name', 'lesson:id,name', 'room:id,name,code,room_type',
            'parallelGroup:id,name',
        ];
    }
}
