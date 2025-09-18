<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Employee;
use App\Helpers\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Display leave management page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = LeaveRequest::with(['employee.department', 'approvedBy']);

        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        $leaveRequests = $query->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $employees = Employee::with('department')->get();
        $departments = \App\Models\Department::all();

        // Calculate summary statistics
        $summary = $this->calculateLeaveSummary($leaveRequests);

        // Get leave balances for current year
        $leaveBalances = LeaveBalance::with('employee')
            ->where('year', now()->year)
            ->get()
            ->groupBy('employee_id');

        return view('attendance.leave-management', compact('leaveRequests', 'employees', 'departments', 'summary', 'leaveBalances', 'user'));
    }

    /**
     * Store a new leave request
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $request->validate([
            'leave_type' => 'required|in:vacation,sick,personal,emergency,maternity,paternity,bereavement,study',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $daysRequested = $startDate->diffInDays($endDate) + 1;

        // Check leave balance
        $leaveBalance = $employee->getLeaveBalanceForYear(now()->year);
        if (!$leaveBalance) {
            return response()->json(['error' => 'Leave balance not found for current year.'], 400);
        }

        $availableDays = $this->getAvailableLeaveDays($leaveBalance, $request->leave_type);
        if ($availableDays < $daysRequested) {
            return response()->json(['error' => 'Insufficient leave balance. Available: ' . $availableDays . ' days.'], 400);
        }

        // Check for overlapping leave requests
        $overlappingRequest = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->first();

        if ($overlappingRequest) {
            return response()->json(['error' => 'You already have a leave request for this period.'], 400);
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => $request->leave_type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_requested' => $daysRequested,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_request' => $leaveRequest->load('employee.department'),
        ]);
    }

    /**
     * Update leave request status (approve/reject)
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        
        // Check if user has permission to approve/reject
        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['error' => 'This leave request has already been processed.'], 400);
        }

        $leaveRequest->update([
            'status' => $request->status,
            'approved_by' => $user->id,
            'approved_at' => TimezoneHelper::now(),
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null,
        ]);

        // If approved, update leave balance
        if ($request->status === 'approved') {
            $this->updateLeaveBalance($leaveRequest);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request ' . $request->status . ' successfully',
            'leave_request' => $leaveRequest->fresh(['employee.department', 'approvedBy']),
        ]);
    }

    /**
     * Cancel a leave request
     */
    public function cancel($id)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $leaveRequest = LeaveRequest::where('id', $id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        if (!in_array($leaveRequest->status, ['pending', 'approved'])) {
            return response()->json(['error' => 'Only pending or approved leave requests can be cancelled.'], 400);
        }

        // If it was approved, restore leave balance
        if ($leaveRequest->status === 'approved') {
            $this->restoreLeaveBalance($leaveRequest);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled successfully',
        ]);
    }

    /**
     * Get leave balance for employee
     */
    public function getLeaveBalance(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $year = $request->get('year', now()->year);
        $leaveBalance = $employee->getLeaveBalanceForYear($year);

        if (!$leaveBalance) {
            return response()->json(['error' => 'Leave balance not found for the specified year.'], 404);
        }

        return response()->json([
            'leave_balance' => $leaveBalance,
            'available_days' => $this->getAllAvailableLeaveDays($leaveBalance),
        ]);
    }

    /**
     * Get leave statistics
     */
    public function getStatistics(Request $request)
    {
        $date = $request->get('date', today());
        $date = Carbon::parse($date);

        $stats = [
            'today' => $this->getTodayLeaveStats($date),
            'this_week' => $this->getWeekLeaveStats($date),
            'this_month' => $this->getMonthLeaveStats($date),
        ];

        return response()->json($stats);
    }

    /**
     * Calculate leave summary
     */
    private function calculateLeaveSummary($leaveRequests)
    {
        $total = $leaveRequests->count();
        $pending = $leaveRequests->where('status', 'pending')->count();
        $approved = $leaveRequests->where('status', 'approved')->count();
        $rejected = $leaveRequests->where('status', 'rejected')->count();
        $totalDays = $leaveRequests->where('status', 'approved')->sum('days_requested');

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total_days' => $totalDays,
        ];
    }

    /**
     * Get available leave days for a specific type
     */
    private function getAvailableLeaveDays($leaveBalance, $leaveType)
    {
        $totalField = $leaveType . '_days_total';
        $usedField = $leaveType . '_days_used';
        
        return $leaveBalance->$totalField - $leaveBalance->$usedField;
    }

    /**
     * Get all available leave days
     */
    private function getAllAvailableLeaveDays($leaveBalance)
    {
        $leaveTypes = ['vacation', 'sick', 'personal', 'emergency', 'maternity', 'paternity', 'bereavement', 'study'];
        $available = [];

        foreach ($leaveTypes as $type) {
            $available[$type] = $this->getAvailableLeaveDays($leaveBalance, $type);
        }

        return $available;
    }

    /**
     * Update leave balance when request is approved
     */
    private function updateLeaveBalance($leaveRequest)
    {
        $leaveBalance = $leaveRequest->employee->getLeaveBalanceForYear($leaveRequest->start_date->year);
        if (!$leaveBalance) {
            return;
        }

        $usedField = $leaveRequest->leave_type . '_days_used';
        $leaveBalance->increment($usedField, $leaveRequest->days_requested);
    }

    /**
     * Restore leave balance when request is cancelled
     */
    private function restoreLeaveBalance($leaveRequest)
    {
        $leaveBalance = $leaveRequest->employee->getLeaveBalanceForYear($leaveRequest->start_date->year);
        if (!$leaveBalance) {
            return;
        }

        $usedField = $leaveRequest->leave_type . '_days_used';
        $leaveBalance->decrement($usedField, $leaveRequest->days_requested);
    }

    private function getTodayLeaveStats($date)
    {
        $requests = LeaveRequest::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('status', 'approved')
            ->get();
        
        return [
            'employees_on_leave' => $requests->count(),
            'leave_types' => $requests->groupBy('leave_type')->map->count(),
        ];
    }

    private function getWeekLeaveStats($date)
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $requests = LeaveRequest::where('start_date', '<=', $weekEnd)
            ->where('end_date', '>=', $weekStart)
            ->where('status', 'approved')
            ->get();
        
        return [
            'total_requests' => $requests->count(),
            'total_days' => $requests->sum('days_requested'),
        ];
    }

    private function getMonthLeaveStats($date)
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $requests = LeaveRequest::where('start_date', '<=', $monthEnd)
            ->where('end_date', '>=', $monthStart)
            ->where('status', 'approved')
            ->get();
        
        return [
            'total_requests' => $requests->count(),
            'total_days' => $requests->sum('days_requested'),
            'average_days_per_request' => $requests->avg('days_requested') ?? 0,
        ];
    }
}
