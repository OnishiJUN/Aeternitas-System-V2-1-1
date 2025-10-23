<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'level',
        'department_id',
        'min_salary',
        'max_salary',
        'is_active',
        'requirements',
        'responsibilities'
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'requirements' => 'array',
        'responsibilities' => 'array'
    ];

    /**
     * Scope to get only active positions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by department
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department_id', $department);
    }

    /**
     * Get the department that owns the position
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope to filter by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get formatted salary range
     */
    public function getSalaryRangeAttribute()
    {
        if ($this->min_salary && $this->max_salary) {
            return '$' . number_format($this->min_salary, 0) . ' - $' . number_format($this->max_salary, 0);
        }
        return 'Salary not specified';
    }
}
