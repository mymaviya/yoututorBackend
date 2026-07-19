<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimetableRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'room_type' => ['nullable', Rule::in(TimetableRoom::TYPES)],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TimetableRoom::query()
            ->where('subscription_id', $subscriptionId)
            ->withCount('timetableEntries')
            ->when($data['room_type'] ?? null, fn ($query, $type) => $query->where('room_type', $type))
            ->when(
                array_key_exists('is_active', $data),
                fn ($query) => $query->where('is_active', $data['is_active'])
            )
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($scope) use ($search) {
                    $scope->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
            })
            ->ordered();

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);
        $data['subscription_id'] = $subscriptionId;

        $room = TimetableRoom::query()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Timetable room created successfully.',
            'data' => $room,
        ], 201);
    }

    public function show(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $this->ensureOwned($request, $timetableRoom);

        return response()->json([
            'success' => true,
            'data' => $timetableRoom->loadCount('timetableEntries'),
        ]);
    }

    public function update(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $this->ensureOwned($request, $timetableRoom);
        $timetableRoom->update($this->validatedData(
            $request,
            (int) $timetableRoom->subscription_id,
            $timetableRoom
        ));

        return response()->json([
            'success' => true,
            'message' => 'Timetable room updated successfully.',
            'data' => $timetableRoom->fresh(),
        ]);
    }

    public function destroy(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $this->ensureOwned($request, $timetableRoom);

        if ($timetableRoom->timetableEntries()->active()->exists()) {
            throw ValidationException::withMessages([
                'room' => 'This room is used by active timetable entries and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $timetableRoom->delete();

        return response()->json([
            'success' => true,
            'message' => 'Timetable room deleted successfully.',
        ]);
    }

    public function activate(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $this->ensureOwned($request, $timetableRoom);
        $timetableRoom->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable room activated successfully.',
            'data' => $timetableRoom->fresh(),
        ]);
    }

    public function deactivate(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $this->ensureOwned($request, $timetableRoom);
        $timetableRoom->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable room deactivated successfully.',
            'data' => $timetableRoom->fresh(),
        ]);
    }

    private function validatedData(
        Request $request,
        int $subscriptionId,
        ?TimetableRoom $room = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('timetable_rooms', 'name')
                    ->where('subscription_id', $subscriptionId)
                    ->ignore($room?->getKey()),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('timetable_rooms', 'code')
                    ->where('subscription_id', $subscriptionId)
                    ->ignore($room?->getKey()),
            ],
            'room_type' => ['required', Rule::in(TimetableRoom::TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'supported_subject_ids' => ['nullable', 'array'],
            'supported_subject_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id')->where('subscription_id', $subscriptionId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function ensureOwned(Request $request, TimetableRoom $room): void
    {
        abort_unless(
            (int) $room->subscription_id === $this->subscriptionId($request),
            404
        );
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }
}