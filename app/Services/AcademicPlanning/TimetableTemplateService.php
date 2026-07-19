<?php

namespace App\Services\AcademicPlanning;

use App\Models\TimetableTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableTemplateService
{
    public function create(int $subscriptionId, array $data): TimetableTemplate
    {
        return DB::transaction(function () use ($subscriptionId, $data) {
            $this->ensureNoEffectiveOverlap($subscriptionId, $data);

            if (! empty($data['is_default'])) {
                $this->clearDefault($subscriptionId);
            }

            return TimetableTemplate::query()->create(array_merge($data, [
                'subscription_id' => $subscriptionId,
            ]));
        });
    }

    public function update(TimetableTemplate $template, array $data): TimetableTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $merged = array_merge($template->only([
                'name',
                'type',
                'effective_from',
                'effective_to',
                'is_default',
                'is_active',
            ]), $data);

            $this->ensureNoEffectiveOverlap(
                (int) $template->subscription_id,
                $merged,
                (int) $template->getKey()
            );

            if (! empty($merged['is_default'])) {
                $this->clearDefault((int) $template->subscription_id, (int) $template->getKey());
            }

            $template->update($data);

            return $template->fresh();
        });
    }

    public function activate(TimetableTemplate $template, bool $makeDefault = false): TimetableTemplate
    {
        return DB::transaction(function () use ($template, $makeDefault) {
            if ($makeDefault) {
                $this->clearDefault((int) $template->subscription_id, (int) $template->getKey());
            }

            $template->update([
                'is_active' => true,
                'is_default' => $makeDefault || $template->is_default,
            ]);

            return $template->fresh();
        });
    }

    public function deactivate(TimetableTemplate $template): TimetableTemplate
    {
        $template->update([
            'is_active' => false,
            'is_default' => false,
        ]);

        return $template->fresh();
    }

    public function clone(TimetableTemplate $source, array $overrides): TimetableTemplate
    {
        return DB::transaction(function () use ($source, $overrides) {
            $data = array_merge(
                $source->only([
                    'type',
                    'effective_from',
                    'effective_to',
                    'is_active',
                ]),
                [
                    'name' => $source->name . ' Copy',
                    'is_default' => false,
                ],
                $overrides
            );

            return $this->create((int) $source->subscription_id, $data);
        });
    }

    public function delete(TimetableTemplate $template): void
    {
        if ($template->weeklyTimetables()->exists()) {
            throw ValidationException::withMessages([
                'template' => 'This template is already used by one or more weekly timetables and cannot be deleted.',
            ]);
        }

        $template->delete();
    }

    private function clearDefault(int $subscriptionId, ?int $exceptId = null): void
    {
        TimetableTemplate::query()
            ->where('subscription_id', $subscriptionId)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function ensureNoEffectiveOverlap(
        int $subscriptionId,
        array $data,
        ?int $exceptId = null
    ): void {
        if (empty($data['is_active'])) {
            return;
        }

        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;
        $type = $data['type'] ?? TimetableTemplate::TYPE_REGULAR;

        $overlap = TimetableTemplate::query()
            ->where('subscription_id', $subscriptionId)
            ->where('type', $type)
            ->where('is_active', true)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->where(function (Builder $query) use ($from, $to) {
                $query
                    ->where(function (Builder $startQuery) use ($to) {
                        $startQuery->whereNull('effective_from');

                        if ($to !== null) {
                            $startQuery->orWhereDate('effective_from', '<=', $to);
                        }
                    })
                    ->where(function (Builder $endQuery) use ($from) {
                        $endQuery->whereNull('effective_to');

                        if ($from !== null) {
                            $endQuery->orWhereDate('effective_to', '>=', $from);
                        }
                    });
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => 'An active template of this type already overlaps the selected effective dates.',
            ]);
        }
    }
}
