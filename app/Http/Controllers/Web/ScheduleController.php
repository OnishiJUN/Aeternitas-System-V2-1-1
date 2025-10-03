<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSchedule;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * Get current filter state from request
     */
    private function getFilterState(Request $request)
    {
        return array_filter([
            'department_id' => $request->get('department_id'),
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'search' => $request->get('search')
        ]);
    }

    /**
     * Build URL with current filters for back navigation
     */
    private function buildBackUrl($filters)
    {
        $params = array_filter($filters, function($value) {
            return !is_null($value) && $value !== '';
        });
        
        return route('schedule.index', $params);
    }
    /**
     * Display the schedule management page
     */
    public function index(Request $request)
    {
        $selectedDepartment = $request->get('department_id');
        $selectedYear = $request->get('year', now()->year);
        $selectedMonth = $request->get('month', now()->month);
        $searchQuery = $request->get('search');

        // Get departments for filter
        $departments = Department::all();

        // Get employees for selected department with search
        $employees = Employee::with('department')
            ->when($selectedDepartment, function($query) use ($selectedDepartment) {
                return $query->where('department_id', $selectedDepartment);
            })
            ->when($searchQuery, function($query) use ($searchQuery) {
                return $query->where(function($q) use ($searchQuery) {
                    $q->where('first_name', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('last_name', 'LIKE', "%{$searchQuery}%")
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchQuery}%"]);
                });
            })
            ->orderBy('first_name')
            ->get();

        // If no department selected, get all employees for the bulk modal
        $allEmployees = Employee::with('department')->orderBy('first_name')->get();

        // Get schedules for the selected month
        $schedules = collect();
        if ($selectedDepartment || $searchQuery) {
            $schedules = EmployeeSchedule::with(['employee', 'department'])
                ->whereYear('date', $selectedYear)
                ->whereMonth('date', $selectedMonth)
                ->when($selectedDepartment, function($query) use ($selectedDepartment) {
                    return $query->where('department_id', $selectedDepartment);
                })
                ->when($searchQuery, function($query) use ($searchQuery) {
                    return $query->whereHas('employee', function($q) use ($searchQuery) {
                        $q->where('first_name', 'LIKE', "%{$searchQuery}%")
                          ->orWhere('last_name', 'LIKE', "%{$searchQuery}%")
                          ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchQuery}%"]);
                    });
                })
                ->get()
                ->keyBy(function($schedule) {
                    return $schedule->employee_id . '_' . $schedule->date->format('Y-m-d');
                });
        }

        // Generate calendar days for the month
        $calendarDays = $this->generateCalendarDays($selectedYear, $selectedMonth);

        $user = Auth::user();

        return view('schedule.index', compact(
            'departments',
            'employees',
            'allEmployees',
            'schedules',
            'calendarDays',
            'selectedDepartment',
            'selectedYear',
            'selectedMonth',
            'searchQuery',
            'user'
        ));
    }

    /**
     * Show the form for creating a new schedule
     */
    public function create(Request $request)
    {
        $employeeId = $request->get('employee_id');
        $date = $request->get('date', now()->format('Y-m-d'));

        $employee = null;
        if ($employeeId) {
            $employee = Employee::with('department')->find($employeeId);
        }

        $employees = Employee::with('department')->orderBy('first_name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        $user = Auth::user();

        // Get current filter state for back navigation
        $currentFilters = $this->getFilterState($request);

        return view('schedule.create', compact('employee', 'employees', 'departments', 'date', 'user', 'currentFilters'));
    }

    /**
     * Store a newly created schedule
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'time_in' => 'sometimes|nullable|date_format:H:i',
            'time_out' => 'sometimes|nullable|date_format:H:i',
            'status' => 'required|in:Working,Day Off,Leave,Holiday,Overtime',
            'notes' => 'nullable|string|max:500'
        ]);

        // Additional validation: if time_out is provided, it should be after time_in
        if ($request->time_in && $request->time_out) {
            $timeIn = \Carbon\Carbon::createFromFormat('H:i', $request->time_in);
            $timeOut = \Carbon\Carbon::createFromFormat('H:i', $request->time_out);
            
            if ($timeOut->lte($timeIn)) {
                return redirect()->back()
                    ->withErrors(['time_out' => 'Time out must be after time in.'])
                    ->withInput();
            }
        }

        // Get employee to get department_id
        $employee = Employee::find($request->employee_id);

        // Check if schedule already exists for this employee and date
        $existingSchedule = EmployeeSchedule::where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->first();

        if ($existingSchedule) {
            return redirect()->back()
                ->withErrors(['date' => 'A schedule already exists for this employee on this date.'])
                ->withInput();
        }

        EmployeeSchedule::create([
            'employee_id' => $request->employee_id,
            'department_id' => $employee->department_id,
            'date' => $request->date,
            'time_in' => $request->time_in,
            'time_out' => $request->time_out,
            'status' => $request->status,
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);

        // Get current filter state for redirect
        $filters = $this->getFilterState($request);

        return redirect()->route('schedule.index', $filters)
            ->with('success', 'Schedule created successfully.');
    }

    /**
     * Display the specified schedule
     */
    public function show(Request $request, EmployeeSchedule $schedule)
    {
        $schedule->load(['employee', 'department', 'creator']);
        $user = Auth::user();

        // Get current filter state for back navigation
        $currentFilters = $this->getFilterState($request);

        return view('schedule.show', compact('schedule', 'user', 'currentFilters'));
    }

    /**
     * Show the form for editing the specified schedule
     */
    public function edit(Request $request, EmployeeSchedule $schedule)
    {
        $schedule->load(['employee', 'department']);
        $user = Auth::user();

        // Get current filter state for back navigation
        $currentFilters = $this->getFilterState($request);

        return view('schedule.edit', compact('schedule', 'user', 'currentFilters'));
    }

    /**
     * Update the specified schedule
     */
    public function update(Request $request, EmployeeSchedule $schedule)
    {
        $request->validate([
            'time_in' => 'sometimes|nullable|date_format:H:i',
            'time_out' => 'sometimes|nullable|date_format:H:i',
            'status' => 'required|in:Working,Day Off,Leave,Holiday,Overtime',
            'notes' => 'nullable|string|max:500'
        ]);

        // Additional validation: if time_out is provided, it should be after time_in
        if ($request->time_in && $request->time_out) {
            $timeIn = \Carbon\Carbon::createFromFormat('H:i', $request->time_in);
            $timeOut = \Carbon\Carbon::createFromFormat('H:i', $request->time_out);
            
            if ($timeOut->lte($timeIn)) {
                return redirect()->back()
                    ->withErrors(['time_out' => 'Time out must be after time in.'])
                    ->withInput();
            }
        }

        $schedule->update([
            'time_in' => $request->time_in,
            'time_out' => $request->time_out,
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        // Get current filter state for redirect
        $filters = $this->getFilterState($request);

        return redirect()->route('schedule.index', $filters)
            ->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified schedule
     */
    public function destroy(Request $request, EmployeeSchedule $schedule)
    {
        $schedule->delete();

        // Get current filter state for redirect
        $filters = $this->getFilterState($request);

        return redirect()->route('schedule.index', $filters)
            ->with('success', 'Schedule deleted successfully.');
    }

    /**
     * Bulk create schedules for multiple employees
     */
    public function bulkCreate(Request $request)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_in' => 'sometimes|nullable|date_format:H:i',
            'time_out' => 'sometimes|nullable|date_format:H:i',
            'status' => 'required|in:Working,Day Off,Leave,Holiday,Overtime',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id'
        ]);

        // Additional validation: end date must be in the same month as start date
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        
        if ($startDate->month !== $endDate->month || $startDate->year !== $endDate->year) {
            return redirect()->back()
                ->withErrors(['end_date' => 'The end date cannot be earlier than the start date. Please choose an end date within the same month as the selected start date.'])
                ->withInput();
        }

        // Additional validation: if time_out is provided, it should be after time_in
        if ($request->time_in && $request->time_out) {
            $timeIn = \Carbon\Carbon::createFromFormat('H:i', $request->time_in);
            $timeOut = \Carbon\Carbon::createFromFormat('H:i', $request->time_out);
            
            if ($timeOut->lte($timeIn)) {
                return redirect()->back()
                    ->withErrors(['time_out' => 'Time out must be after time in.'])
                    ->withInput();
            }
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $employeeIds = $request->employee_ids;

        $createdCount = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            
            // Create schedule for each day in the range
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Skip if schedule already exists
                $existingSchedule = EmployeeSchedule::where('employee_id', $employeeId)
                    ->where('date', $date->format('Y-m-d'))
                    ->first();

                if (!$existingSchedule) {
                    EmployeeSchedule::create([
                        'employee_id' => $employeeId,
                        'department_id' => $employee->department_id,
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $request->time_in,
                        'time_out' => $request->time_out,
                        'status' => $request->status,
                        'created_by' => Auth::id(),
                    ]);
                    $createdCount++;
                }
            }
        }

        // Get current filter state for redirect
        $filters = $this->getFilterState($request);

        return redirect()->route('schedule.index', $filters)
            ->with('success', "Successfully created {$createdCount} schedule entries.");
    }

    /**
     * Generate calendar days for a given month
     */
    private function generateCalendarDays($year, $month)
    {
        $days = [];
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $days[] = [
                'day' => $day,
                'date' => $date,
                'is_weekend' => $date->isWeekend(),
                'is_today' => $date->isToday(),
            ];
        }

        return $days;
    }

    /**
     * Get schedule statistics for dashboard
     */
    public function getStatistics(Request $request)
    {
        $departmentId = $request->get('department_id');
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $query = EmployeeSchedule::whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $schedules = $query->get();

        $statistics = [
            'total_schedules' => $schedules->count(),
            'working_days' => $schedules->where('status', 'Working')->count(),
            'leave_days' => $schedules->where('status', 'Leave')->count(),
            'day_off' => $schedules->where('status', 'Day Off')->count(),
            'holidays' => $schedules->where('status', 'Holiday')->count(),
            'overtime' => $schedules->where('status', 'Overtime')->count(),
        ];

        return response()->json($statistics);
    }
}