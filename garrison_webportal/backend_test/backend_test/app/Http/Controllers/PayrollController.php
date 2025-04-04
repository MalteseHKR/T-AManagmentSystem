<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        // Static list of payroll records for demonstration purposes
        $payrolls = [
            ['id' => 1, 'employee_name' => 'John Doe', 'month' => 'January', 'basic_salary' => 3000, 'allowances' => 500, 'deductions' => 200, 'net_salary' => 3300],
            ['id' => 2, 'employee_name' => 'Jane Smith', 'month' => 'January', 'basic_salary' => 3200, 'allowances' => 400, 'deductions' => 150, 'net_salary' => 3450],
            // Add more payroll records as needed
        ];

        // Filter payroll records based on the request parameters
        if ($request->has('employee_name')) {
            $payrolls = array_filter($payrolls, function ($payroll) use ($request) {
                return stripos($payroll['employee_name'], $request->input('employee_name')) !== false;
            });
        }

        if ($request->has('month') && $request->input('month') !== '') {
            $payrolls = array_filter($payrolls, function ($payroll) use ($request) {
                return $payroll['month'] === $request->input('month');
            });
        }

        // Get the currency symbol based on the current locale
        $currencySymbol = __('currency.symbol');

        return view('payroll', ['payrolls' => $payrolls, 'currencySymbol' => $currencySymbol]);
    }
}
