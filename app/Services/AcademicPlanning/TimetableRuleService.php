<?php

namespace App\Services\AcademicPlanning;

use App\Models\TimetableRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableRuleService
{
    public function create(int $subscriptionId, array $data): TimetableRule
    {
        return DB::transaction(function () use ($subscriptionId, $data) {
            $this->ensureUniqueKey(
                $subscriptionId,
                $data['rule_key'],
                $data['academic_year_id'] ?? null
            );

            return TimetableRule::query()->create(
                $this->prepare($data, $subscriptionId)
            );
        });
    }

    public function update(TimetableRule $rule, array $data): TimetableRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $this->ensureUniqueKey(
                (int) $rule->subscription_id,
                $data['rule_key'],
                $data['academic_year_id'] ?? null,
                (int) $rule->id
            );

            $rule->update($this->prepare($data, (int) $rule->subscription_id));

            return $rule->fresh('academicYear');
        });
    }

    public function duplicate(TimetableRule $rule, array $overrides): TimetableRule
    {
        $data = array_merge(
            Arr::only($rule->toArray(), [
                'academic_year_id',
                'rule_key',
                'rule_value',
                'value_type',
                'constraint_type',
                'priority',
                'description',
                'effective_from',
                'effective_to',
                'is_active',
            ]),
            $overrides
        );

        return $this->create((int) $rule->subscription_id, $data);
    }

    public function setActive(TimetableRule $rule, bool $active): TimetableRule
    {
        $rule->update(['is_active' => $active]);

        return $rule->fresh('academicYear');
    }

    public function delete(TimetableRule $rule): void
    {
        $rule->delete();
    }

    private function prepare(array $data, int $subscriptionId): array
    {
        return array_merge($data, [
            'subscription_id' => $subscriptionId,
            'rule_value' => $this->serialiseValue(
                $data['rule_value'],
                $data['value_type']
            ),
            'constraint_type' => $data['constraint_type'] ?? 'soft',
            'priority' => $data['priority'] ?? 5,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    private function serialiseValue(mixed $value, string $valueType): string
    {
        return match ($valueType) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer' => (string) ((int) $value),
            'decimal' => (string) ((float) $value),
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => trim((string) $value),
        };
    }

    private function ensureUniqueKey(
        int $subscriptionId,
        string $ruleKey,
        ?int $academicYearId,
        ?int $ignoreId = null
    ): void {
        $exists = TimetableRule::query()
            ->withoutGlobalScopes()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('rule_key', $ruleKey)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rule_key' => 'This rule key already exists for the selected academic year.',
            ]);
        }
    }
}
