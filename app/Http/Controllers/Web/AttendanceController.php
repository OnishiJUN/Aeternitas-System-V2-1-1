<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\AttendanceSetting;
use App\Models\AttendanceException;
use App\Helpers\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display daily attendance page
     */
    public function daily(Request $request)
    {
        $date = $request->get('date', today()->format('Y-m-d'));
        $date = Carbon::parse($date);
        
        // Paginate employees
        $employees = Employee::with(['department', 'account'])
            ->whereHas('account', function($query) {
                $query->where('is_active', true);
            })
            ->orderBy('first_name')
            ->paginate(10); // 10 employees per page

        // Get all attendance records for the date (for summary calculation)
        $allAttendanceRecords = AttendanceRecord::with(['employee.department'])
            ->where('date', $date)
            ->get()
            ->keyBy('employee_id');

        // Get attendance records for current page employees only
        $employeeIds = $employees->pluck('id');
        $attendanceRecords = AttendanceRecord::with(['employee.department'])
            ->where('date', $date)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        // Calculate summary statistics using all records
        $summary = $this->calculateDailySummary($allAttendanceRecords);

        $user = Auth::user();
        
        return view('attendance.daily', compact('employees', 'attendanceRecords', 'summary', 'date', 'user'));
    }

    /**
     * Display timekeeping page
     */
    public function timekeeping(Request $request)
    {
        $query = AttendanceRecord::with(['employee.department', 'employee.account']);

        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $attendanceRecords = $query->orderBy('date', 'desc')
            ->orderBy('time_in', 'desc')
            ->paginate(20);

        // Calculate summary statistics
        $allRecords = $query->get();
        $summary = $this->calculateTimekeepingSummary($allRecords);

        $employees = Employee::with('department')->get();
        $departments = \App\Models\Department::all();
        $user = Auth::user();

        return view('attendance.timekeeping', compact('attendanceRecords', 'employees', 'departments', 'summary', 'user'));
    }

    /**
     * Display schedule page
     */
    public function schedule(Request $request)
    {
        $week = $request->get('week', now()->format('Y-\WW'));
        $weekStart = Carbon::parse($week . '1'); // Start of week (Monday)
        $weekEnd = $weekStart->copy()->addDays(6);

        $employees = Employee::with(['workSchedules' => function($query) use ($weekStart, $weekEnd) {
            $query->where('is_active', true)
                  ->where('effective_date', '<=', $weekEnd)
                  ->where(function($q) use ($weekStart) {
                      $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $weekStart);
                  });
        }])->get();

        $user = Auth::user();

        return view('attendance.schedule', compact('employees', 'weekStart', 'weekEnd', 'user'));
    }

    /**
     * Calculate daily attendance summary
     */
    private function calculateDailySummary($attendanceRecords)
    {
        $total = $attendanceRecords->count();
        $present = $attendanceRecords->where('status', 'present')->count();
        $absent = $attendanceRecords->where('status', 'absent')->count();
        $late = $attendanceRecords->where('status', 'late')->count();
        $halfDay = $attendanceRecords->where('status', 'half_day')->count();

        $attendanceRate = $total > 0 ? round(($present + $late + $halfDay) / $total * 100, 1) : 0;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'half_day' => $halfDay,
            'attendance_rate' => $attendanceRate,
        ];
    }

    /**
     * Calculate timekeeping summary
     */
    private function calculateTimekeepingSummary($attendanceRecords)
    {
        $totalHours = $attendanceRecords->sum('total_hours');
        $regularHours = $attendanceRecords->where('total_hours', '<=', 8)->sum('total_hours');
        $overtimeHours = $attendanceRecords->where('total_hours', '>', 8)->sum(function($record) {
            return max(0, $record->total_hours - 8);
        });
        
        $totalRecords = $attendanceRecords->count();
        $averageHours = $totalRecords > 0 ? round($totalHours / $totalRecords, 1) : 0;

        return [
            'total_hours' => $totalHours,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'average_hours' => $averageHours,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Get attendance statistics for dashboard
     */
    public function getStatistics(Request $request)
    {
        $date = $request->get('date', today());
        $date = Carbon::parse($date);

        $stats = [
            'today' => $this->getTodayStats($date),
            'this_week' => $this->getWeekStats($date),
            'this_month' => $this->getMonthStats($date),
        ];

        return response()->json($stats);
    }

    private function getTodayStats($date)
    {
        $records = AttendanceRecord::where('date', $date)->get();
        
        return [
            'total_employees' => Employee::whereHas('account', function($q) {
                $q->where('is_active', true);
            })->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'total_hours' => $records->sum('total_hours'),
            'overtime_hours' => $records->sum('overtime_hours'),
        ];
    }

    private function getWeekStats($date)
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $records = AttendanceRecord::whereBetween('date', [$weekStart, $weekEnd])->get();
        
        return [
            'total_hours' => $records->sum('total_hours'),
            'overtime_hours' => $records->sum('overtime_hours'),
            'average_daily_hours' => $records->avg('total_hours') ?? 0,
        ];
    }

    private function getMonthStats($date)
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $records = AttendanceRecord::whereBetween('date', [$monthStart, $monthEnd])->get();
        
        return [
            'total_hours' => $records->sum('total_hours'),
            'overtime_hours' => $records->sum('overtime_hours'),
            'working_days' => $records->where('status', '!=', 'absent')->count(),
        ];
    }
}
