<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => 'YouTutor ERP',
                'group' => 'general',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'site_tagline',
                'value' => 'Assessment Management Platform',
                'group' => 'general',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'company_name',
                'value' => 'Maviya IT Services',
                'group' => 'general',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'mhmasti@gmail.com',
                'group' => 'contact',
                'type' => 'email',
                'is_public' => true,
            ],
            [
                'key' => 'contact_phone',
                'value' => '',
                'group' => 'contact',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'business_address',
                'value' => 'Siddharth Nagar, Uttar Pradesh, India',
                'group' => 'contact',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'key' => 'support_text',
                'value' => 'Demo, Installation, Training & Customization',
                'group' => 'contact',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'gst_number',
                'value' => '',
                'group' => 'business',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'facebook_url',
                'value' => '',
                'group' => 'social',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'linkedin_url',
                'value' => '',
                'group' => 'social',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'youtube_url',
                'value' => '',
                'group' => 'social',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'razorpay_key_id',
                'value' => '',
                'group' => 'payment',
                'type' => 'password',
                'is_public' => false,
            ],
            [
                'key' => 'razorpay_key_secret',
                'value' => '',
                'group' => 'payment',
                'type' => 'password',
                'is_public' => false,
            ],
            [
                'key' => 'site_logo',
                'value' => '/logo.png',
                'group' => 'branding',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'site_favicon',
                'value' => '/favicon.ico',
                'group' => 'branding',
                'type' => 'url',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
