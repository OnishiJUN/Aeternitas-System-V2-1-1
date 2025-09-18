<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Department;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get department IDs
        $hrDept = Department::where('name', 'Human Resources')->first();
        $itDept = Department::where('name', 'Information Technology')->first();
        $financeDept = Department::where('name', 'Finance')->first();
        $marketingDept = Department::where('name', 'Marketing')->first();
        $opsDept = Department::where('name', 'Operations')->first();

        $employees = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone' => '+63-917-123-4567',
                'department_id' => $itDept->id,
                'position' => 'Senior Software Developer',
                'salary' => 85000.00, // PHP 85,000
                'hire_date' => '2022-03-15',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'phone' => '+63-917-234-5678',
                'department_id' => $hrDept->id,
                'position' => 'HR Manager',
                'salary' => 75000.00, // PHP 75,000
                'hire_date' => '2021-08-20',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Brown',
                'phone' => '+63-917-345-6789',
                'department_id' => $financeDept->id,
                'position' => 'Financial Analyst',
                'salary' => 65000.00, // PHP 65,000
                'hire_date' => '2023-01-10',
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'phone' => '+63-917-456-7890',
                'department_id' => $marketingDept->id,
                'position' => 'Marketing Specialist',
                'salary' => 55000.00, // PHP 55,000
                'hire_date' => '2022-11-05',
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Wilson',
                'phone' => '+63-917-567-8901',
                'department_id' => $opsDept->id,
                'position' => 'Operations Manager',
                'salary' => 80000.00, // PHP 80,000
                'hire_date' => '2021-05-12',
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Anderson',
                'phone' => '+63-917-678-9012',
                'department_id' => $itDept->id,
                'position' => 'System Administrator',
                'salary' => 70000.00, // PHP 70,000
                'hire_date' => '2022-07-18',
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Taylor',
                'phone' => '+63-917-789-0123',
                'department_id' => $financeDept->id,
                'position' => 'Accountant',
                'salary' => 60000.00, // PHP 60,000
                'hire_date' => '2023-02-28',
            ],
            [
                'first_name' => 'Jennifer',
                'last_name' => 'Martinez',
                'phone' => '+63-917-890-1234',
                'department_id' => $hrDept->id,
                'position' => 'Recruitment Specialist',
                'salary' => 58000.00, // PHP 58,000
                'hire_date' => '2022-09-14',
            ],
        ];

        foreach ($employees as $employee) {
            Employee::create($employee);
        }

        $this->command->info('Employees created successfully!');
    }
}
