<?php

namespace App\Services\AcademicPlanning;

use App\Models\TimetableRule;
use Illuminate\Support\Collection;

class TimetableConstraintResolver
{
    private Collection $rules;

    public function load(
        int $subscriptionId,
        ?int $academicYearId,
        string|\DateTimeInterface|null $effectiveDate = null
    ): self {
        $this->rules = TimetableRule::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->forAcademicYear($academicYearId)
            ->effectiveOn($effectiveDate)
            ->ordered()
            ->get();

        return $this;
    }

    public function integer(string $key, int $default): int
    {
        $value = $this->value($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->value($key, $default);

        return is_bool($value)
            ? $value
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function array(string $key): array
    {
        $value = $this->value($key, []);

        return is_array($value) ? $value : [];
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $rule = $this->rule($key);

        return $rule?->typed_value ?? $default;
    }

    public function isHard(string $key): bool
    {
        return $this->rule($key)?->constraint_type === 'hard';
    }

    public function priority(string $key, int $default = 5): int
    {
        return (int) ($this->rule($key)?->priority ?? $default);
    }

    public function blockedClassSlots(): array
    {
        return $this->normaliseSlots($this->array('class.blocked_slots'));
    }

    public function blockedTeacherSlots(): array
    {
        return $this->normaliseSlots($this->array('teacher.blocked_slots'), true);
    }

    public function blockedSubjectSlots(): array
    {
        return $this->normaliseSlots($this->array('subject.blocked_slots'), false, true);
    }

    public function appliedRules(): array
    {
        return $this->rules
            ->map(fn (TimetableRule $rule) => [
                'id' => $rule->id,
                'rule_key' => $rule->rule_key,
                'constraint_type' => $rule->constraint_type,
                'priority' => $rule->priority,
                'value' => $rule->typed_value,
            ])
            ->values()
            ->all();
    }

    private function rule(string $key): ?TimetableRule
    {
        return $this->rules
            ->first(fn (TimetableRule $rule) => $rule->rule_key === $key);
    }

    private function normaliseSlots(
        array $slots,
        bool $requireTeacher = false,
        bool $requireSubject = false
    ): array {
        return collect($slots)
            ->filter(fn ($slot) => is_array($slot))
            ->filter(fn (array $slot) => isset($slot['weekday'], $slot['school_bell_id']))
            ->filter(fn (array $slot) => ! $requireTeacher || isset($slot['teacher_id']))
            ->filter(fn (array $slot) => ! $requireSubject || isset($slot['subject_id']))
            ->map(fn (array $slot) => [
                'weekday' => (int) $slot['weekday'],
                'school_bell_id' => (int) $slot['school_bell_id'],
                'teacher_id' => isset($slot['teacher_id']) ? (int) $slot['teacher_id'] : null,
                'subject_id' => isset($slot['subject_id']) ? (int) $slot['subject_id'] : null,
            ])
            ->values()
            ->all();
    }
}
