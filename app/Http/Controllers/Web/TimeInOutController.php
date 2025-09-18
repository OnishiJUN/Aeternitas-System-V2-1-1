<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceLog;
use App\Models\AttendanceSetting;
use App\Models\AttendanceException;
use App\Helpers\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TimeInOutController extends Controller
{
    /**
     * Display time in/out page
     */
    public function index()
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return redirect()->route('dashboard')->with('error', 'Employee record not found.');
        }

        // Get today's attendance record
        $todayAttendance = $employee->getTodayAttendance();
        
        // Get recent activity (last 5 days)
        $recentActivity = $employee->attendanceRecords()
            ->where('date', '>=', today()->subDays(5))
            ->orderBy('date', 'desc')
            ->get();

        return view('attendance.time-in-out', compact('user', 'todayAttendance', 'recentActivity'));
    }

    /**
     * Handle time in
     */
    public function timeIn(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $today = today();
        
        // Check if already timed in today
        $existingRecord = $employee->getTodayAttendance();
        if ($existingRecord && $existingRecord->time_in) {
            return response()->json(['error' => 'You have already timed in today.'], 400);
        }

        // Check if it's a working day
        if (!$this->isWorkingDay($today)) {
            return response()->json(['error' => 'Today is not a working day.'], 400);
        }

        $currentTime = TimezoneHelper::now();

        // Create or update attendance record
        $attendanceRecord = AttendanceRecord::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $today,
            ],
            [
                'time_in' => $currentTime,
                'status' => 'present',
            ]
        );

        // Log the action
        $this->logAttendanceAction($attendanceRecord, 'time_in', null, [
            'time_in' => $currentTime->toDateTimeString(),
        ], 'Employee timed in');

        // Calculate if late
        $isLate = $this->checkIfLate($employee, $today, $currentTime);
        if ($isLate) {
            $attendanceRecord->update(['status' => 'late']);
        }

        return response()->json([
            'success' => true,
            'message' => $isLate ? 'Time in recorded (Late arrival)' : 'Time in recorded successfully',
            'time_in' => $currentTime->format('H:i:s'),
            'is_late' => $isLate,
            'attendance_record' => $attendanceRecord->fresh(),
        ]);
    }

    /**
     * Handle time out
     */
    public function timeOut(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $today = today();
        
        // Get today's attendance record
        $attendanceRecord = $employee->getTodayAttendance();
        if (!$attendanceRecord || !$attendanceRecord->time_in) {
            return response()->json(['error' => 'You must time in first before timing out.'], 400);
        }

        if ($attendanceRecord->time_out) {
            return response()->json(['error' => 'You have already timed out today.'], 400);
        }

        $currentTime = TimezoneHelper::now();

        // Calculate hours worked
        $totalHours = $this->calculateTotalHours($attendanceRecord->time_in, $currentTime, $attendanceRecord->break_start, $attendanceRecord->break_end);
        $hoursBreakdown = $this->calculateRegularAndOvertimeHours($totalHours);

        // Update attendance record
        $oldValues = [
            'time_out' => null,
            'total_hours' => $attendanceRecord->total_hours,
            'regular_hours' => $attendanceRecord->regular_hours,
            'overtime_hours' => $attendanceRecord->overtime_hours,
        ];

        $attendanceRecord->update([
            'time_out' => $currentTime,
            'total_hours' => $totalHours,
            'regular_hours' => $hoursBreakdown['regular_hours'],
            'overtime_hours' => $hoursBreakdown['overtime_hours'],
            'status' => $this->calculateStatus($totalHours, $attendanceRecord->time_in),
        ]);

        // Log the action
        $this->logAttendanceAction($attendanceRecord, 'time_out', $oldValues, [
            'time_out' => $currentTime->toDateTimeString(),
            'total_hours' => $totalHours,
            'regular_hours' => $hoursBreakdown['regular_hours'],
            'overtime_hours' => $hoursBreakdown['overtime_hours'],
        ], 'Employee timed out');

        return response()->json([
            'success' => true,
            'message' => 'Time out recorded successfully',
            'time_out' => $currentTime->format('H:i:s'),
            'total_hours' => $totalHours,
            'regular_hours' => $hoursBreakdown['regular_hours'],
            'overtime_hours' => $hoursBreakdown['overtime_hours'],
            'attendance_record' => $attendanceRecord->fresh(),
        ]);
    }

    /**
     * Handle break start
     */
    public function breakStart(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $attendanceRecord = $employee->getTodayAttendance();
        if (!$attendanceRecord || !$attendanceRecord->time_in) {
            return response()->json(['error' => 'You must time in first before starting break.'], 400);
        }

        if ($attendanceRecord->break_start) {
            return response()->json(['error' => 'Break already started.'], 400);
        }

        $currentTime = TimezoneHelper::now();

        $attendanceRecord->update(['break_start' => $currentTime]);

        // Log the action
        $this->logAttendanceAction($attendanceRecord, 'break_start', null, [
            'break_start' => $currentTime->toDateTimeString(),
        ], 'Employee started break');

        return response()->json([
            'success' => true,
            'message' => 'Break started',
            'break_start' => $currentTime->format('H:i:s'),
        ]);
    }

    /**
     * Handle break end
     */
    public function breakEnd(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $attendanceRecord = $employee->getTodayAttendance();
        if (!$attendanceRecord || !$attendanceRecord->break_start) {
            return response()->json(['error' => 'You must start break first before ending it.'], 400);
        }

        if ($attendanceRecord->break_end) {
            return response()->json(['error' => 'Break already ended.'], 400);
        }

        $currentTime = TimezoneHelper::now();

        $attendanceRecord->update(['break_end' => $currentTime]);

        // Log the action
        $this->logAttendanceAction($attendanceRecord, 'break_end', null, [
            'break_end' => $currentTime->toDateTimeString(),
        ], 'Employee ended break');

        return response()->json([
            'success' => true,
            'message' => 'Break ended',
            'break_end' => $currentTime->format('H:i:s'),
        ]);
    }

    /**
     * Get current attendance status
     */
    public function getStatus()
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $attendanceRecord = $employee->getTodayAttendance();
        
        if (!$attendanceRecord) {
            return response()->json([
                'status' => 'not_started',
                'message' => 'Ready to clock in',
                'can_time_in' => true,
                'can_time_out' => false,
                'can_break_start' => false,
                'can_break_end' => false,
            ]);
        }

        $canTimeIn = !$attendanceRecord->time_in;
        $canTimeOut = $attendanceRecord->time_in && !$attendanceRecord->time_out;
        $canBreakStart = $attendanceRecord->time_in && !$attendanceRecord->time_out && !$attendanceRecord->break_start;
        $canBreakEnd = $attendanceRecord->break_start && !$attendanceRecord->break_end;

        return response()->json([
            'status' => $attendanceRecord->status,
            'time_in' => $attendanceRecord->time_in?->format('H:i:s'),
            'time_out' => $attendanceRecord->time_out?->format('H:i:s'),
            'break_start' => $attendanceRecord->break_start?->format('H:i:s'),
            'break_end' => $attendanceRecord->break_end?->format('H:i:s'),
            'total_hours' => $attendanceRecord->total_hours,
            'can_time_in' => $canTimeIn,
            'can_time_out' => $canTimeOut,
            'can_break_start' => $canBreakStart,
            'can_break_end' => $canBreakEnd,
        ]);
    }

    /**
     * Check if a date is a working day
     */
    private function isWorkingDay($date)
    {
        // Check if it's a holiday
        if (AttendanceException::isHoliday($date)) {
            return false;
        }

        // Check if it's a special working day (weekend work)
        if (AttendanceException::isSpecialWorkingDay($date)) {
            return true;
        }

        // Check if it's weekend
        if ($date->isWeekend()) {
            return false;
        }

        return true;
    }

    /**
     * Check if employee is late
     */
    private function checkIfLate($employee, $date, $timeIn)
    {
        $schedule = $employee->getWorkScheduleForDate($date);
        if (!$schedule) {
            return false;
        }

        $dayOfWeek = strtolower($date->format('l'));
        $expectedStartTime = $schedule->{$dayOfWeek . '_start'};
        
        if (!$expectedStartTime) {
            return false;
        }

        $gracePeriod = AttendanceSetting::getValue('grace_period_minutes', 15);
        $expectedTime = Carbon::parse($date->format('Y-m-d') . ' ' . $expectedStartTime);
        $actualTime = $timeIn;

        return $actualTime->gt($expectedTime->addMinutes($gracePeriod));
    }

    /**
     * Calculate total hours worked
     */
    private function calculateTotalHours($timeIn, $timeOut, $breakStart = null, $breakEnd = null)
    {
        $totalMinutes = $timeOut->diffInMinutes($timeIn);
        
        // Subtract break time if exists
        if ($breakStart && $breakEnd) {
            $breakMinutes = $breakEnd->diffInMinutes($breakStart);
            $totalMinutes -= $breakMinutes;
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Calculate regular and overtime hours
     */
    private function calculateRegularAndOvertimeHours($totalHours)
    {
        $regularHoursLimit = AttendanceSetting::getValue('regular_hours_limit', 8);
        $regularHours = min($totalHours, $regularHoursLimit);
        $overtimeHours = max(0, $totalHours - $regularHoursLimit);

        return [
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
        ];
    }

    /**
     * Calculate attendance status
     */
    private function calculateStatus($totalHours, $timeIn)
    {
        if ($totalHours < 4) {
            return 'half_day';
        }

        // Check if late (this would need the schedule check)
        return 'present';
    }

    /**
     * Log attendance action
     */
    private function logAttendanceAction($attendanceRecord, $action, $oldValues, $newValues, $reason)
    {
        AttendanceLog::create([
            'attendance_record_id' => $attendanceRecord->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'performed_by' => Auth::id(),
            'performed_at' => TimezoneHelper::now(),
            'reason' => $reason,
        ]);
    }
}
