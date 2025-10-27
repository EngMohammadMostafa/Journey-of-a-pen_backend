<?php

namespace Database\Seeders; 

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('reading_platform_users')->insert([
            'username' => 'platform_admin',
            'email' => 'admin@readingplatform.com',
            'password' => Hash::make('Admin123!'),
            'age' => 30,
            'gender' => 'male',
            'user_type' => 2, 
            'points' => 0,
            'purchases_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ تم إنشاء الأدمن بنجاح!');
        $this->command->info('📧 البريد: admin@readingplatform.com');
        $this->command->info('🔑 كلمة المرور: Admin123!');
        $this->command->info('👤 نوع المستخدم: 2 (admin)');
    }
    
}