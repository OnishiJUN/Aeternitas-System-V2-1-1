<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\AttendanceSetting;
use App\Helpers\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OvertimeController extends Controller
{
    /**
     * Display overtime management page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = OvertimeRequest::with(['employee.department', 'approvedBy']);

        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $overtimeRequests = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $employees = Employee::with('department')->get();
        $departments = \App\Models\Department::all();

        // Calculate summary statistics
        $summary = $this->calculateOvertimeSummary($overtimeRequests);

        return view('attendance.overtime', compact('overtimeRequests', 'employees', 'departments', 'summary', 'user'));
    }

    /**
     * Store a new overtime request
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'required|string|max:500',
        ]);

        // Check if there's already an overtime request for this date
        $existingRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $request->date)
            ->first();

        if ($existingRequest) {
            return response()->json(['error' => 'You already have an overtime request for this date.'], 400);
        }

        // Calculate hours
        $startDateTime = Carbon::parse($request->date . ' ' . $request->start_time);
        $endDateTime = Carbon::parse($request->date . ' ' . $request->end_time);
        $hours = $endDateTime->diffInHours($startDateTime);

        // Get overtime rate multiplier
        $rateMultiplier = AttendanceSetting::getValue('overtime_rate_multiplier', 1.5);

        $overtimeRequest = OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => $request->date,
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'hours' => $hours,
            'rate_multiplier' => $rateMultiplier,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request submitted successfully',
            'overtime_request' => $overtimeRequest->load('employee.department'),
        ]);
    }

    /**
     * Update overtime request status (approve/reject)
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

        $overtimeRequest = OvertimeRequest::findOrFail($id);

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['error' => 'This overtime request has already been processed.'], 400);
        }

        $overtimeRequest->update([
            'status' => $request->status,
            'approved_by' => $user->id,
            'approved_at' => TimezoneHelper::now(),
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request ' . $request->status . ' successfully',
            'overtime_request' => $overtimeRequest->fresh(['employee.department', 'approvedBy']),
        ]);
    }

    /**
     * Cancel an overtime request
     */
    public function cancel($id)
    {
        $user = Auth::user();
        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['error' => 'Employee record not found.'], 404);
        }

        $overtimeRequest = OvertimeRequest::where('id', $id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['error' => 'Only pending overtime requests can be cancelled.'], 400);
        }

        $overtimeRequest->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request cancelled successfully',
        ]);
    }

    /**
     * Get overtime statistics
     */
    public function getStatistics(Request $request)
    {
        $date = $request->get('date', today());
        $date = Carbon::parse($date);

        $stats = [
            'today' => $this->getTodayOvertimeStats($date),
            'this_week' => $this->getWeekOvertimeStats($date),
            'this_month' => $this->getMonthOvertimeStats($date),
        ];

        return response()->json($stats);
    }

    /**
     * Calculate overtime summary
     */
    private function calculateOvertimeSummary($overtimeRequests)
    {
        $total = $overtimeRequests->count();
        $pending = $overtimeRequests->where('status', 'pending')->count();
        $approved = $overtimeRequests->where('status', 'approved')->count();
        $rejected = $overtimeRequests->where('status', 'rejected')->count();
        $totalHours = $overtimeRequests->where('status', 'approved')->sum('hours');

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total_hours' => $totalHours,
        ];
    }

    private function getTodayOvertimeStats($date)
    {
        $requests = OvertimeRequest::where('date', $date)->get();
        
        return [
            'total_requests' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'total_hours' => $requests->where('status', 'approved')->sum('hours'),
        ];
    }

    private function getWeekOvertimeStats($date)
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $requests = OvertimeRequest::whereBetween('date', [$weekStart, $weekEnd])->get();
        
        return [
            'total_requests' => $requests->count(),
            'approved_hours' => $requests->where('status', 'approved')->sum('hours'),
            'pending_requests' => $requests->where('status', 'pending')->count(),
        ];
    }

    private function getMonthOvertimeStats($date)
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $requests = OvertimeRequest::whereBetween('date', [$monthStart, $monthEnd])->get();
        
        return [
            'total_requests' => $requests->count(),
            'approved_hours' => $requests->where('status', 'approved')->sum('hours'),
            'average_daily_hours' => $requests->where('status', 'approved')->avg('hours') ?? 0,
        ];
    }
}
