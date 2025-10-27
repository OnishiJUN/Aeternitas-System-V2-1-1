<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Department;
use App\Services\PayrollGenerationService;
use App\Helpers\CompanyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollGenerationService $payrollService)
    {
        $this->payrollService = $payrollService;
    }
    public function index(Request $request)
    {
        $currentCompany = CompanyHelper::getCurrentCompany();
        
        $query = Payroll::with('employee.department');
        
        // Filter by company
        if ($currentCompany) {
            $query->whereHas('employee', function($q) use ($currentCompany) {
                $q->where('company_id', $currentCompany->id);
            });
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('month')) {
            $query->whereMonth('pay_period_start', $request->month);
        }

        if ($request->has('year')) {
            $query->whereYear('pay_period_start', $request->year);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payrolls = $query->orderBy('pay_period_start', 'desc')->paginate(15);
        
        $employeesQuery = Employee::query();
        if ($currentCompany) {
            $employeesQuery->forCompany($currentCompany->id);
        }
        $employees = $employeesQuery->get();

        // Calculate summary statistics
        $allPayrolls = $query->get();
        $summary = [
            'total_employees' => $employeesQuery->count(),
            'gross_pay' => $allPayrolls->sum('gross_pay'),
            'total_deductions' => $allPayrolls->sum('deductions'),
            'net_pay' => $allPayrolls->sum('net_pay'),
            'pending_count' => $allPayrolls->where('status', 'pending')->count(),
            'approved_count' => $allPayrolls->where('status', 'approved')->count(),
            'paid_count' => $allPayrolls->where('status', 'paid')->count(),
        ];

        return view('payroll.index', compact('payrolls', 'employees', 'summary'));
    }

    public function create()
    {
        $currentCompany = CompanyHelper::getCurrentCompany();
        
        $employeesQuery = Employee::query();
        if ($currentCompany) {
            $employeesQuery->forCompany($currentCompany->id);
        }
        $employees = $employeesQuery->get();
        
        return view('payrolls.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'basic_salary' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        Payroll::create($request->all());

        return redirect()->route('payrolls.index')
            ->with('success', 'Payroll created successfully.');
    }

    public function show(Payroll $payroll)
    {
        $payroll->load('employee.department');
        return view('payrolls.show', compact('payroll'));
    }

    public function edit(Payroll $payroll)
    {
        $currentCompany = CompanyHelper::getCurrentCompany();
        
        $employeesQuery = Employee::query();
        if ($currentCompany) {
            $employeesQuery->forCompany($currentCompany->id);
        }
        $employees = $employeesQuery->get();
        
        return view('payrolls.edit', compact('payroll', 'employees'));
    }

    public function update(Request $request, Payroll $payroll)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'basic_salary' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        $payroll->update($request->all());

        return redirect()->route('payrolls.index')
            ->with('success', 'Payroll updated successfully.');
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();

        return redirect()->route('payrolls.index')
            ->with('success', 'Payroll deleted successfully.');
    }

    public function process(Payroll $payroll)
    {
        // Calculate net pay
        $grossPay = $payroll->basic_salary + 
                   ($payroll->overtime_hours * $payroll->overtime_rate) + 
                   $payroll->bonuses;

        $netPay = $grossPay - $payroll->deductions - $payroll->tax_amount;

        $payroll->update([
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', 'Payroll processed successfully.');
    }

    public function summary()
    {
        $summary = DB::table('payrolls')
            ->select([
                DB::raw('COUNT(*) as total_payrolls'),
                DB::raw('SUM(gross_pay) as total_gross_pay'),
                DB::raw('SUM(net_pay) as total_net_pay'),
                DB::raw('AVG(gross_pay) as average_gross_pay'),
                DB::raw('AVG(net_pay) as average_net_pay'),
            ])
            ->where('status', 'processed')
            ->first();

        $monthly_data = DB::table('payrolls')
            ->select([
                DB::raw('YEAR(pay_period_start) as year'),
                DB::raw('MONTH(pay_period_start) as month'),
                DB::raw('SUM(gross_pay) as total_gross_pay'),
                DB::raw('SUM(net_pay) as total_net_pay'),
                DB::raw('COUNT(*) as payroll_count'),
            ])
            ->where('status', 'processed')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return view('payrolls.summary', compact('summary', 'monthly_data'));
    }

    public function monthlyReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->select([
                'departments.name as department_name',
                DB::raw('COUNT(payrolls.id) as employee_count'),
                DB::raw('SUM(payrolls.gross_pay) as total_gross_pay'),
                DB::raw('SUM(payrolls.net_pay) as total_net_pay'),
            ])
            ->whereYear('payrolls.pay_period_start', $request->year)
            ->whereMonth('payrolls.pay_period_start', $request->month)
            ->where('payrolls.status', 'processed')
            ->groupBy('departments.id', 'departments.name')
            ->get();

        return view('payrolls.monthly-report', compact('report', 'request'));
    }

    /**
     * Show form to generate payroll from period management
     */
    public function generateFromPeriod()
    {
        // Get recent periods from database
        $periods = \App\Models\Period::with('department')
            ->orderBy('created_at', 'desc')
            ->get();
        $employees = Employee::with('department')->get();
        $departments = Department::all();

        return view('payroll.generate-from-period', compact('periods', 'employees', 'departments'));
    }

    /**
     * Generate payroll from period management data
     */
public function generateFromPeriodData(Request $request)
    {
        $request->validate([
            'period_id' => 'required|string',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            // Get period data from database
            $period = \App\Models\Period::find($request->period_id);

            if (!$period) {
                return redirect()->back()->with('error', 'Period not found.');
            }

            // Convert period to array format for the service
            $periodData = [
                'id' => $period->id,
                'name' => $period->name,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'department_id' => $period->department_id,
                'employee_ids' => $period->employee_ids,
            ];

            // Get comprehensive attendance data for the period
            $startDate = Carbon::parse($period->start_date);
            $endDate = Carbon::parse($period->end_date);
            
            // Get employees for the period
            $employees = Employee::with('department');
            if (!empty($period->department_id)) {
                $employees = $employees->where('department_id', $period->department_id);
            }
            if (!empty($period->employee_ids) && is_array($period->employee_ids)) {
                $employees = $employees->whereIn('id', $period->employee_ids);
            }
            $employees = $employees->get();
            
            // Get comprehensive attendance data
            $comprehensiveData = $this->getComprehensiveAttendanceData($startDate, $endDate, $employees);
            
            // Generate payroll using comprehensive data
            $generatedPayrolls = $this->payrollService->generatePayrollFromComprehensiveData(
                $periodData, 
                $comprehensiveData,
                $request->employee_ids
            );

            if (empty($generatedPayrolls)) {
                return redirect()->back()->with('error', 'No payroll records were generated.');
            }

            return redirect()->route('payroll.index')
                ->with('success', 'Payroll generated successfully for ' . count($generatedPayrolls) . ' employees.');

        } catch (\Exception $e) {
            \Log::error('Payroll generation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate payroll: ' . $e->getMessage());
        }
    }

    /**
     * Get comprehensive attendance data for all employees in the period
     * 
     * @param Carbon $startDate Start date of the period
     * @param Carbon $endDate End date of the period
     * @param Collection $employees Collection of employees to analyze
     * @return array Comprehensive attendance data array
     */
    private function getComprehensiveAttendanceData($startDate, $endDate, $employees)
    {
        $comprehensiveData = [];
        
        foreach ($employees as $employee) {
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->format('Y-m-d');
                
                // Get attendance record for this date
                $attendanceRecord = \App\Models\AttendanceRecord::where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();
                
                // Get schedule for this date
                $schedule = \App\Models\EmployeeSchedule::where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();
                
                // Determine schedule status
                $scheduleStatus = $this->getScheduleStatus($schedule);
                
                // Determine attendance status
                $attendanceStatus = $this->getAttendanceStatus($attendanceRecord, $schedule);
                
                // Initialize default values for non-working days
                $workedHours = '—';
                $scheduledHours = '—';
                $morningOvertime = 0;
                $eveningOvertime = 0;
                $overtime = 0;
                $nightDifferentialHours = 0;
                $lateMinutes = 0;
                $isNightShift = false;
                
                // Only calculate attendance metrics if schedule status is 'Working' or 'Regular Holiday' or 'Special Holiday'
                if (in_array($scheduleStatus, ['Working', 'Regular Holiday', 'Special Holiday'])) {
                    
                    // Calculate worked hours if attendance record exists
                    if ($attendanceRecord && $attendanceRecord->time_in && $attendanceRecord->time_out) {
                        $workedHours = $attendanceRecord->total_hours ?? 0;
                        $scheduledHours = $this->formatHours($workedHours);
                        
                        // Calculate overtime
                        if ($workedHours > 8) {
                            $overtime = $workedHours - 8;
                        }
                        
                        // Calculate night differential hours
                        $nightDifferentialHours = $attendanceRecord->calculateNightShiftHours();
                        $isNightShift = $nightDifferentialHours > 0;
                        
                        // Calculate late minutes
                        if ($attendanceRecord->isLate()) {
                            $lateMinutes = $attendanceRecord->late_minutes ?? 0;
                        }
                    } else {
                        // No attendance record - mark as absent
                        $attendanceStatus = 'Absent';
                        $scheduledHours = '—';
                    }
                } else {
                    // Non-working day
                    $scheduledHours = $scheduleStatus;
                }
                
                // Add to comprehensive data
                $comprehensiveData[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'employee_id_number' => $employee->employee_id,
                    'department' => $employee->department->name ?? 'N/A',
                    'date' => $dateStr,
                    'date_formatted' => $currentDate->format('M j, Y'),
                    'day_of_week' => $currentDate->format('l'),
                    'schedule_status' => $scheduleStatus,
                    'attendance_status' => $attendanceStatus,
                    'scheduled_hours' => $scheduledHours,
                    'worked_hours' => $workedHours,
                    'overtime' => $overtime,
                    'morning_overtime' => $morningOvertime,
                    'evening_overtime' => $eveningOvertime,
                    'night_differential_hours' => $nightDifferentialHours,
                    'late_minutes' => $lateMinutes,
                    'is_night_shift' => $isNightShift,
                ];
                
                $currentDate->addDay();
            }
        }
        
        return $comprehensiveData;
    }
    
    /**
     * Determine schedule status for a given schedule
     */
    private function getScheduleStatus($schedule)
    {
        if (!$schedule) {
            return 'Day Off';
        }
        
        // Check if it's a holiday
        if ($schedule->is_holiday) {
            return $schedule->holiday_type === 'regular' ? 'Regular Holiday' : 'Special Holiday';
        }
        
        // Check if it's a leave day
        if ($schedule->is_leave) {
            return 'Leave';
        }
        
        // Check if it's a working day
        if ($schedule->is_working_day) {
            return 'Working';
        }
        
        return 'Day Off';
    }
    
    /**
     * Determine attendance status based on attendance record and schedule
     */
    private function getAttendanceStatus($attendanceRecord, $schedule)
    {
        if (!$attendanceRecord) {
            return 'Absent';
        }
        
        if ($attendanceRecord->time_in && $attendanceRecord->time_out) {
            $totalHours = $attendanceRecord->total_hours ?? 0;
            
            if ($totalHours < 4) {
                return 'Half Day';
            }
            
            if ($attendanceRecord->isLate()) {
                return 'Late';
            }
            
            return 'Present';
        }
        
        if ($attendanceRecord->time_in && !$attendanceRecord->time_out) {
            return 'Present (No Time Out)';
        }
        
        return 'Absent';
    }
    
    /**
     * Format hours for display
     */
    private function formatHours($hours)
    {
        if ($hours == 0) {
            return '—';
        }
        
        $wholeHours = floor($hours);
        $minutes = round(($hours - $wholeHours) * 60);
        
        if ($minutes == 0) {
            return $wholeHours . ' hrs';
        }
        
        return $wholeHours . ' hrs ' . $minutes . ' mins';
    }

    /**
     * Show payroll details for a specific period
     */
    public function showPeriodPayroll(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $payrolls = Payroll::where('pay_period_start', $startDate->format('Y-m-d'))
            ->where('pay_period_end', $endDate->format('Y-m-d'))
            ->with('employee.department')
            ->get();

        $summary = $this->payrollService->getPayrollSummary($startDate, $endDate);

        return view('payroll.period-details', compact('payrolls', 'summary', 'startDate', 'endDate'));
    }





    /**
     * Update payroll status
     */
    public function updateStatus(Request $request, Payroll $payroll)
    {
        $request->validate([
            'status' => 'required|in:pending,processed,paid,cancelled',
        ]);

        $payroll->update([
            'status' => $request->status,
            'processed_at' => $request->status === 'processed' ? now() : $payroll->processed_at,
        ]);

        return redirect()->back()->with('success', 'Payroll status updated successfully.');
    }

    /**
     * Export payroll data
     */
    public function export(Request $request)
    {
        // Implementation for payroll export
        return response()->json(['message' => 'Export functionality to be implemented']);
    }
}
