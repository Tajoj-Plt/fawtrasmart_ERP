<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class EmployeesTableSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        for ($i = 0; $i < 10; $i++) {
            DB::table('employees')->insert([
                'first_name' => $faker->firstName,
                'middle_name' => $faker->lastName,
                'nickname' => $faker->userName,
                'email' => $faker->unique()->safeEmail,
                'employee_type' => 1,
                'status' => 1,
                'allow_system_access' => 0,
                'send_credentials' => 0,
                'language' => 1,
                'Job_role_id' => 1,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}