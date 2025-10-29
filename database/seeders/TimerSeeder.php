<?php

namespace Database\Seeders;

use App\Models\Timer;
use App\Models\User;
use Illuminate\Database\Seeder;

class TimerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@example.com')->first();
        if (!$user) {
            return;
        }

        Timer::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Launch Day'],
            [
                'timer_type' => 'fixed_date',
                'target_datetime' => now()->addHours(2),
                'duration_seconds' => null,
                'skin' => 'skin01',
                'text_position' => 'top',
                'custom_text' => 'Big Launch!',
                'gif_url' => '',
                'cache_key' => null,
                'status' => 'active',
            ]
        );

        Timer::firstOrCreate(
            ['user_id' => $user->id, 'title' => '10-min Promo'],
            [
                'timer_type' => 'reload_based',
                'target_datetime' => null,
                'duration_seconds' => 600,
                'skin' => 'skin03',
                'text_position' => 'bottom',
                'custom_text' => 'Limited Time Offer',
                'gif_url' => '',
                'cache_key' => null,
                'status' => 'active',
            ]
        );
    }
}


