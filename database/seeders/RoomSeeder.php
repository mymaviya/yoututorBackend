<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Subscription::all() as $subscription) {
            foreach (range(1, 10) as $roomNo) {
                Room::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'name' => 'Room '.$roomNo,
                    ],
                    [
                        'room_no' => (string) $roomNo,
                        'type' => 'classroom',
                        'capacity' => 50,

                        'total_rows' => 5,
                        'total_columns' => 10,
                        'total_seats' => 50,
                        'exam_usable_seats' => 25,

                        'allow_exam_seating' => true,
                        'is_shared' => false,
                        'is_active' => true,
                    ]
                );
            }

            Room::updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'name' => 'Computer Lab',
                ],
                [
                    'room_no' => 'LAB-1',
                    'type' => 'computer_lab',
                    'capacity' => 40,
                    'total_rows' => 4,
                    'total_columns' => 10,
                    'total_seats' => 40,
                    'exam_usable_seats' => 20,
                    'allow_exam_seating' => true,
                    'is_shared' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}