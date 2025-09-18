<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = Payroll::with('employee.department');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('month')) {
            $query->whereMonth('pay_period_start', $request->month);
        }

        if ($request->has('year')) {
            $query->whereYear('pay_period_start', $request->year);
        }

        $payrolls = $query->orderBy('pay_period_start', 'desc')->paginate(15);
        return response()->json($payrolls);
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

        $payroll = Payroll::create($request->all());

        return response()->json($payroll, 201);
    }

    public function show(Payroll $payroll)
    {
        $payroll->load('employee.department');
        return response()->json($payroll);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $request->validate([
            'employee_id' => 'sometimes|required|exists:employees,id',
            'pay_period_start' => 'sometimes|required|date',
            'pay_period_end' => 'sometimes|required|date|after:pay_period_start',
            'basic_salary' => 'sometimes|required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        $payroll->update($request->all());

        return response()->json($payroll);
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();

        return response()->json(null, 204);
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

        return response()->json($payroll);
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

        return response()->json($summary);
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

        return response()->json($report);
    }
}
