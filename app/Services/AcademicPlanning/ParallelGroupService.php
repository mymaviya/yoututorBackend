<?php

namespace App\Services\AcademicPlanning;

use App\Models\ParallelGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParallelGroupService
{
    public function create(int $subscriptionId, array $data): ParallelGroup
    {
        return DB::transaction(function () use ($subscriptionId, $data): ParallelGroup {
            $items = Arr::pull($data, 'items', []);

            $group = ParallelGroup::query()->create(array_merge($data, [
                'subscription_id' => $subscriptionId,
            ]));

            $this->syncItems($group, $items);

            return $group->fresh(['grade', 'items.subject', 'items.teacher']);
        });
    }

    public function update(ParallelGroup $group, array $data): ParallelGroup
    {
        return DB::transaction(function () use ($group, $data): ParallelGroup {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $group->fill($data)->save();

            if ($hasItems) {
                $this->syncItems($group, $items);
            }

            return $group->fresh(['grade', 'items.subject', 'items.teacher']);
        });
    }

    public function delete(ParallelGroup $group): void
    {
        if ($group->timetableEntries()->active()->exists()) {
            throw ValidationException::withMessages([
                'parallel_group' => 'This parallel group is used by active timetable entries and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($group): void {
            $group->items()->delete();
            $group->delete();
        });
    }

    public function setActive(ParallelGroup $group, bool $active): ParallelGroup
    {
        $group->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $group->items()->update(['is_active' => false]);
        }

        return $group->fresh(['grade', 'items.subject', 'items.teacher']);
    }

    private function syncItems(ParallelGroup $group, array $items): void
    {
        $subjectIds = collect($items)
            ->pluck('subject_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($subjectIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'A subject may appear only once in a parallel group.',
            ]);
        }

        $group->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $group->items()->create([
                'subject_id' => (int) $item['subject_id'],
                'teacher_id' => isset($item['teacher_id']) ? (int) $item['teacher_id'] : null,
                'stream_ids' => array_values(array_unique(array_map(
                    'intval',
                    $item['stream_ids'] ?? []
                ))),
                'student_group_name' => $item['student_group_name'] ?? null,
                'teacher_split_order' => (int) ($item['teacher_split_order'] ?? ($index + 1)),
                'room_no' => $item['room_no'] ?? null,
                'is_active' => (bool) ($item['is_active'] ?? true),
            ]);
        }
    }
}
