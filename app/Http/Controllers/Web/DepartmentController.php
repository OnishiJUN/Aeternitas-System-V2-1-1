<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('employees')
            ->when(request('search'), function ($query) {
                $query->where('name', 'like', '%' . request('search') . '%');
            })
            ->paginate(15);

        $user = auth()->user();
        return view('departments.index', compact('departments', 'user'));
    }

    public function create()
    {
        $user = auth()->user();
        return view('departments.create', compact('user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments',
            'description' => 'nullable|string|max:1000',
            'budget' => 'nullable|numeric|min:0',
        ]);

        Department::create($request->all());

        return redirect()->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load('employees');
        $user = auth()->user();
        return view('departments.show', compact('department', 'user'));
    }

    public function edit(Department $department)
    {
        $user = auth()->user();
        return view('departments.edit', compact('department', 'user'));
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'description' => 'nullable|string|max:1000',
            'budget' => 'nullable|numeric|min:0',
        ]);

        $department->update($request->all());

        return redirect()->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    public function employees(Department $department)
    {
        $employees = $department->employees()
            ->with('account')
            ->when(request('search'), function ($query) {
                $query->where('first_name', 'like', '%' . request('search') . '%')
                      ->orWhere('last_name', 'like', '%' . request('search') . '%');
            })
            ->paginate(15);

        $user = auth()->user();
        return view('departments.employees', compact('department', 'employees', 'user'));
    }
}
