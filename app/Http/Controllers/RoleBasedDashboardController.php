<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class RoleBasedDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        switch ($user->role) {
            case 'admin':
                return $this->adminDashboard();
            case 'hr':
                return $this->hrDashboard();
            case 'manager':
                return $this->managerDashboard($user);
            case 'employee':
                return $this->employeeDashboard($user);
            default:
                return $this->employeeDashboard($user);
        }
    }

    private function adminDashboard()
    {
        $stats = [
            'total_employees' => Employee::count(),
            'total_departments' => Department::count(),
            'total_accounts' => Account::count(),
            'total_budget' => Department::sum('budget'),
            'used_budget' => Employee::sum('salary'),
            'remaining_budget' => Department::sum('budget') - Employee::sum('salary'),
            'average_salary' => Employee::avg('salary') ?? 0,
        ];

        $recent_employees = Employee::with('department')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $department_stats = Department::withCount('employees')
            ->withSum('employees', 'salary')
            ->get();

        return view('dashboards.admin', compact('stats', 'recent_employees', 'department_stats'));
    }

    private function hrDashboard()
    {
        $stats = [
            'total_employees' => Employee::count(),
            'total_departments' => Department::count(),
            'new_employees_this_month' => Employee::whereMonth('created_at', now()->month)->count(),
            'average_salary' => Employee::avg('salary') ?? 0,
        ];

        $recent_employees = Employee::with('department')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $department_breakdown = Department::withCount('employees')->get();

        return view('dashboards.hr', compact('stats', 'recent_employees', 'department_breakdown'));
    }

    private function managerDashboard($user)
    {
        // Get the user's department through their employee record
        $employee = $user->employee;
        if (!$employee) {
            return redirect()->route('login')->with('error', 'No employee record found.');
        }

        $department = $employee->department;
        
        $stats = [
            'department_name' => $department->name,
            'total_employees' => $department->employees->count(),
            'total_budget' => $department->budget,
            'used_budget' => $department->employees->sum('salary'),
            'remaining_budget' => $department->budget - $department->employees->sum('salary'),
            'average_salary' => $department->employees->avg('salary') ?? 0,
        ];

        $department_employees = $department->employees()
            ->orderBy('created_at', 'desc')
            ->get();

        $monthly_payroll_summary = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->select([
                DB::raw('YEAR(payrolls.pay_period_start) as year'),
                DB::raw('MONTH(payrolls.pay_period_start) as month'),
                DB::raw('SUM(payrolls.gross_pay) as total_gross_pay'),
                DB::raw('SUM(payrolls.net_pay) as total_net_pay'),
                DB::raw('COUNT(*) as payroll_count'),
            ])
            ->where('employees.department_id', $department->id)
            ->where('payrolls.status', 'processed')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        return view('dashboards.manager', compact(
            'stats',
            'department_employees',
            'monthly_payroll_summary',
            'department'
        ));
    }

    private function employeeDashboard($user)
    {
        $employee = $user->employee;
        if (!$employee) {
            return redirect()->route('login')->with('error', 'No employee record found.');
        }

        $stats = [
            'employee_name' => $employee->full_name,
            'position' => $employee->position,
            'department' => $employee->department->name,
            'salary' => $employee->salary,
            'hire_date' => $employee->hire_date,
            'employee_id' => $employee->employee_id,
        ];

        $recent_payrolls = $employee->payrolls()
            ->orderBy('pay_period_start', 'desc')
            ->limit(5)
            ->get();

        $yearly_summary = DB::table('payrolls')
            ->select([
                DB::raw('YEAR(pay_period_start) as year'),
                DB::raw('SUM(gross_pay) as total_gross_pay'),
                DB::raw('SUM(net_pay) as total_net_pay'),
                DB::raw('COUNT(*) as payroll_count'),
            ])
            ->where('employee_id', $employee->id)
            ->where('status', 'processed')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get();

        return view('dashboards.employee', compact(
            'stats',
            'recent_payrolls',
            'yearly_summary',
            'employee'
        ));
    }
}
