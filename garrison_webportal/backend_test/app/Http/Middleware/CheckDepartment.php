<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckDepartment
{
    /**
     * Handle department-based access control.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $department
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $requiredAccess = null)
    {
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }

        // Get user's department through the proper table joins
        $userDeptInfo = DB::table('login')
            ->join('user_information', 'login.user_id', '=', 'user_information.id')
            ->join('departments', 'user_information.department_id', '=', 'departments.department_id')
            ->where('login.user_login_id', $userId)
            ->select('departments.department_id', 'departments.department')
            ->first();

        // If no department info found, restrict access
        if (!$userDeptInfo) {
            return redirect()->route('login')->with('error', 'Account setup incomplete. Please contact HR.');
        }

        // HR (department_id = 1) has access to everything
        if ($userDeptInfo->department_id == 1) {
            return $next($request);
        }

        // For specific access types
        if ($requiredAccess === 'hr_only' && $userDeptInfo->department_id != 1) {
            return redirect()->route('profile.index')
                ->with('error', 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
