@extends('layouts.app')

@section('title', 'Manage Payroll - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Manage Payroll</h1>
    <p>Calculate and manage payroll, generate payslips, and handle payroll-related functions.</p>

    <!-- Back Link -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-4">Back</a>

    <!-- Filter Form -->
    <form method="GET" action="{{ route('payroll') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-4">
                <input type="text" name="employee_name" class="form-control" placeholder="Search by employee name" value="{{ request('employee_name') }}">
            </div>
            <div class="col-md-4">
                <select name="month" class="form-control">
                    <option value="">All Months</option>
                    <option value="January" {{ request('month') == 'January' ? 'selected' : '' }}>January</option>
                    <option value="February" {{ request('month') == 'February' ? 'selected' : '' }}>February</option>
                    <option value="March" {{ request('month') == 'March' ? 'selected' : '' }}>March</option>
                    <option value="April" {{ request('month') == 'April' ? 'selected' : '' }}>April</option>
                    <option value="May" {{ request('month') == 'May' ? 'selected' : '' }}>May</option>
                    <option value="June" {{ request('month') == 'June' ? 'selected' : '' }}>June</option>
                    <option value="July" {{ request('month') == 'July' ? 'selected' : '' }}>July</option>
                    <option value="August" {{ request('month') == 'August' ? 'selected' : '' }}>August</option>
                    <option value="September" {{ request('month') == 'September' ? 'selected' : '' }}>September</option>
                    <option value="October" {{ request('month') == 'October' ? 'selected' : '' }}>October</option>
                    <option value="November" {{ request('month') == 'November' ? 'selected' : '' }}>November</option>
                    <option value="December" {{ request('month') == 'December' ? 'selected' : '' }}>December</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
        </div>
    </form>

    <!-- Payroll Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Month</th>
                <th>Basic Salary</th>
                <th>Allowances</th>
                <th>Deductions</th>
                <th>Net Salary</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payrolls as $payroll)
            <tr>
                <td>{{ $payroll['employee_name'] }}</td>
                <td>{{ $payroll['month'] }}</td>
                <td>{{ $currencySymbol }}{{ number_format($payroll['basic_salary'], 2) }}</td>
                <td>{{ $currencySymbol }}{{ number_format($payroll['allowances'], 2) }}</td>
                <td>{{ $currencySymbol }}{{ number_format($payroll['deductions'], 2) }}</td>
                <td>{{ $currencySymbol }}{{ number_format($payroll['net_salary'], 2) }}</td>
                <td>
                    <button class="btn btn-success btn-sm">Generate Payslip</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection