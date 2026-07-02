<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@ojas.test'],
            [
                'name' => 'Ojas Admin',
                'phone' => '9999999991',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles([$adminRole]);

        $doctors = [
            [
                'email' => 'aarti@ojas.test',
                'name' => 'Dr. Aarti Mehta',
                'slug' => 'aarti-mehta',
                'phone' => '6304898428',
                'specialization' => 'Clinical Nutritionist',
                'experience' => '12 yrs',
                'rating' => 4.9,
                'bio' => 'Aarti blends evidence-based nutrition with everyday Indian kitchens. Her plans are practical, sustainable, and always personal.',
                'focus_areas' => ['Weight management', 'PCOS', 'Gut health'],
            ],
            [
                'email' => 'rohan@ojas.test',
                'name' => 'Dr. Rohan Iyer',
                'slug' => 'rohan-iyer',
                'phone' => '9999999994',
                'specialization' => 'Diabetologist',
                'experience' => '15 yrs',
                'rating' => 4.8,
                'bio' => 'Rohan helps people reverse pre-diabetes and live well with diabetes through food-first protocols and gentle accountability.',
                'focus_areas' => ['Type 2 diabetes', 'Pre-diabetes', 'Cardio-metabolic'],
            ],
            [
                'email' => 'priya@ojas.test',
                'name' => 'Dr. Priya Nair',
                'slug' => 'priya-nair',
                'phone' => '9999999995',
                'specialization' => 'Sports Dietitian',
                'experience' => '9 yrs',
                'rating' => 4.9,
                'bio' => 'Priya works with athletes and weekend warriors alike, building fueling strategies that respect both performance and recovery.',
                'focus_areas' => ['Performance', 'Muscle gain', 'Recovery'],
            ],
        ];

        foreach ($doctors as $doctorData) {
            $doctor = User::updateOrCreate(
                ['email' => $doctorData['email']],
                array_merge($doctorData, [
                    'role' => 'doctor',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]),
            );
            $doctor->syncRoles([$doctorRole]);
        }

        $customer = User::updateOrCreate(
            ['email' => 'customer@ojas.test'],
            [
                'name' => 'Demo Customer',
                'phone' => '9999999993',
                'role' => 'customer',
                'password' => Hash::make('password'),
                'pincode' => '400001',
                'address_line_1' => '15 Wellness Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'email_verified_at' => now(),
            ],
        );
        $customer->syncRoles([$customerRole]);
    }
}
