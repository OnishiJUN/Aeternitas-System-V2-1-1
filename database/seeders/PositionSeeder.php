<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            [
                'name' => 'Manager',
                'code' => 'MGR',
                'description' => 'Oversees team operations and strategic planning',
                'level' => 'Senior',
                'department' => 'Management',
                'min_salary' => 50000.00,
                'max_salary' => 80000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Business or related field',
                    '5+ years of management experience',
                    'Strong leadership skills',
                    'Excellent communication skills'
                ]),
                'responsibilities' => json_encode([
                    'Lead and manage team members',
                    'Develop and implement strategies',
                    'Monitor team performance',
                    'Make key business decisions'
                ])
            ],
            [
                'name' => 'Rank and File',
                'code' => 'RAF',
                'description' => 'General employee position for various operational tasks',
                'level' => 'Entry',
                'department' => 'Operations',
                'min_salary' => 25000.00,
                'max_salary' => 40000.00,
                'requirements' => json_encode([
                    'High school diploma or equivalent',
                    'Basic computer skills',
                    'Good communication skills',
                    'Willingness to learn'
                ]),
                'responsibilities' => json_encode([
                    'Perform assigned tasks',
                    'Follow company policies',
                    'Maintain work quality standards',
                    'Collaborate with team members'
                ])
            ],
            [
                'name' => 'Software Engineer',
                'code' => 'SE',
                'description' => 'Develops and maintains software applications',
                'level' => 'Mid',
                'department' => 'IT',
                'min_salary' => 40000.00,
                'max_salary' => 70000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Computer Science or related field',
                    'Proficiency in programming languages',
                    'Problem-solving skills',
                    'Experience with software development'
                ]),
                'responsibilities' => json_encode([
                    'Design and develop software applications',
                    'Debug and fix software issues',
                    'Collaborate with development team',
                    'Maintain code quality and documentation'
                ])
            ],
            [
                'name' => 'Senior Software Engineer',
                'code' => 'SSE',
                'description' => 'Senior-level software development with technical leadership',
                'level' => 'Senior',
                'department' => 'IT',
                'min_salary' => 60000.00,
                'max_salary' => 90000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Computer Science',
                    '5+ years of software development experience',
                    'Advanced programming skills',
                    'Mentoring and leadership abilities'
                ]),
                'responsibilities' => json_encode([
                    'Lead technical projects',
                    'Mentor junior developers',
                    'Architect software solutions',
                    'Review and improve code quality'
                ])
            ],
            [
                'name' => 'Human Resources Specialist',
                'code' => 'HRS',
                'description' => 'Manages human resources functions and employee relations',
                'level' => 'Mid',
                'department' => 'Human Resources',
                'min_salary' => 35000.00,
                'max_salary' => 55000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in HR or related field',
                    'Knowledge of labor laws',
                    'Strong interpersonal skills',
                    'Experience in recruitment'
                ]),
                'responsibilities' => json_encode([
                    'Recruit and hire employees',
                    'Manage employee benefits',
                    'Handle employee relations',
                    'Ensure compliance with labor laws'
                ])
            ],
            [
                'name' => 'Accountant',
                'code' => 'ACC',
                'description' => 'Manages financial records and prepares financial reports',
                'level' => 'Mid',
                'department' => 'Finance',
                'min_salary' => 30000.00,
                'max_salary' => 50000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Accounting',
                    'CPA certification preferred',
                    'Knowledge of accounting principles',
                    'Proficiency in accounting software'
                ]),
                'responsibilities' => json_encode([
                    'Prepare financial statements',
                    'Manage accounts payable and receivable',
                    'Conduct financial audits',
                    'Ensure tax compliance'
                ])
            ],
            [
                'name' => 'Administrative Assistant',
                'code' => 'AA',
                'description' => 'Provides administrative support to management and staff',
                'level' => 'Entry',
                'department' => 'Administration',
                'min_salary' => 20000.00,
                'max_salary' => 35000.00,
                'requirements' => json_encode([
                    'High school diploma or equivalent',
                    'Proficiency in Microsoft Office',
                    'Excellent organizational skills',
                    'Strong communication abilities'
                ]),
                'responsibilities' => json_encode([
                    'Schedule meetings and appointments',
                    'Handle correspondence',
                    'Maintain filing systems',
                    'Provide general office support'
                ])
            ],
            [
                'name' => 'Sales Representative',
                'code' => 'SR',
                'description' => 'Promotes and sells company products or services',
                'level' => 'Mid',
                'department' => 'Sales',
                'min_salary' => 25000.00,
                'max_salary' => 45000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Business or related field',
                    'Strong sales skills',
                    'Excellent communication abilities',
                    'Customer service experience'
                ]),
                'responsibilities' => json_encode([
                    'Identify and contact potential customers',
                    'Present product demonstrations',
                    'Negotiate sales contracts',
                    'Maintain customer relationships'
                ])
            ],
            [
                'name' => 'Marketing Specialist',
                'code' => 'MS',
                'description' => 'Develops and implements marketing strategies',
                'level' => 'Mid',
                'department' => 'Marketing',
                'min_salary' => 30000.00,
                'max_salary' => 50000.00,
                'requirements' => json_encode([
                    'Bachelor\'s degree in Marketing or related field',
                    'Creative thinking skills',
                    'Digital marketing knowledge',
                    'Analytical abilities'
                ]),
                'responsibilities' => json_encode([
                    'Develop marketing campaigns',
                    'Analyze market trends',
                    'Manage social media presence',
                    'Coordinate with sales team'
                ])
            ],
            [
                'name' => 'Customer Service Representative',
                'code' => 'CSR',
                'description' => 'Handles customer inquiries and provides support',
                'level' => 'Entry',
                'department' => 'Customer Service',
                'min_salary' => 20000.00,
                'max_salary' => 30000.00,
                'requirements' => json_encode([
                    'High school diploma or equivalent',
                    'Excellent communication skills',
                    'Patience and empathy',
                    'Problem-solving abilities'
                ]),
                'responsibilities' => json_encode([
                    'Respond to customer inquiries',
                    'Resolve customer complaints',
                    'Provide product information',
                    'Maintain customer satisfaction'
                ])
            ]
        ];

        foreach ($positions as $position) {
            \App\Models\Position::create($position);
        }
    }
}
