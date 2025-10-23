<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $positions = Position::with('department')->orderBy('name')->paginate(10);
        $user = auth()->user();
        return view('positions.index', compact('positions', 'user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth()->user();
        $departments = Department::orderBy('name')->get();
        return view('positions.create', compact('user', 'departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:positions,name',
            'code' => 'required|string|max:10|unique:positions,code',
            'description' => 'nullable|string',
            'level' => 'required|in:Entry,Mid,Senior,Lead',
            'department_id' => 'required|exists:departments,id',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
            'requirements' => 'nullable|array',
            'responsibilities' => 'nullable|array'
        ]);

        $position = Position::create($validated);

        return redirect()->route('positions.index')
            ->with('success', 'Position created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position)
    {
        $position->load('department');
        $user = auth()->user();
        return view('positions.show', compact('position', 'user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Position $position)
    {
        $user = auth()->user();
        $departments = Department::orderBy('name')->get();
        return view('positions.edit', compact('position', 'user', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($position->id)],
            'code' => ['required', 'string', 'max:10', Rule::unique('positions', 'code')->ignore($position->id)],
            'description' => 'nullable|string',
            'level' => 'required|in:Entry,Mid,Senior,Lead',
            'department_id' => 'required|exists:departments,id',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
            'requirements' => 'nullable|array',
            'responsibilities' => 'nullable|array'
        ]);

        $position->update($validated);

        return redirect()->route('positions.index')
            ->with('success', 'Position updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        $position->delete();

        return redirect()->route('positions.index')
            ->with('success', 'Position deleted successfully!');
    }
}
