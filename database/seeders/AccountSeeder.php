<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a special admin account (not linked to any employee)
        Account::create([
            'employee_id' => null, // Admin account not linked to employee
            'email' => 'jerson.cerezo.100@gmail.com',
            'password' => Hash::make('jersoncerezo03'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create accounts for all employees
        $employees = Employee::all();
        foreach ($employees as $employee) {
            // Skip if account already exists
            if (Account::where('employee_id', $employee->id)->exists()) {
                continue;
            }

            // Generate email from name
            $email = strtolower($employee->first_name . '.' . $employee->last_name . '@company.com');
            $email = str_replace(' ', '', $email);
            
            // Determine role based on position
            $role = 'employee';
            if (str_contains(strtolower($employee->position), 'manager')) {
                $role = 'manager';
            } elseif (str_contains(strtolower($employee->position), 'hr')) {
                $role = 'hr';
            }

            Account::create([
                'employee_id' => $employee->id,
                'email' => $email,
                'password' => Hash::make('password123'), // Default password
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('Accounts created successfully!');
    }
}
