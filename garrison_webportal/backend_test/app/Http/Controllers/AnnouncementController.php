<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\UserInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    /**
     * Get the real user_id for the currently authenticated user.
     * This handles the mapping from login_id to user_id.
     *
     * @return int|null
     */
    protected function getCurrentUserId()
    {
        // Get the login ID from Auth
        $loginId = Auth::id();
        Log::debug('Auth ID retrieved', ['auth_id' => $loginId]);
        
        // Look up the user_id in the login table
        $loginRecord = DB::table('login')->where('user_login_id', $loginId)->first();
        
        if (!$loginRecord) {
            Log::warning("No login record found for login_id: $loginId");
            return null;
        }
        
        Log::debug('User ID retrieved from login table', ['user_id' => $loginRecord->user_id]);
        return $loginRecord->user_id;
    }
    
    /**
     * Get user information for the currently authenticated user.
     *
     * @return UserInformation|null
     */
    protected function getCurrentUserInfo()
    {
        $userId = $this->getCurrentUserId();
        
        Log::info('Getting current user info', ['user_id' => $userId]);
        
        if (!$userId) {
            Log::warning('No user ID found when getting user info');
            return null;
        }
        
        // Enable query log for this call
        DB::enableQueryLog();
        
        // Use eager loading to retrieve related department and role
        $userInfo = UserInformation::with(['department', 'role'])->find($userId);
        
        // Log the queries that were executed
        $queries = DB::getQueryLog();
        Log::info('User info queries', ['queries' => $queries]);
        
        if (!$userInfo) {
            Log::warning('User info not found', ['user_id' => $userId]);
            return null;
        }
        
        Log::info('User info retrieved successfully', [
            'user_id' => $userInfo->user_id,
            'user_name' => $userInfo->user_name,
            'user_surname' => $userInfo->user_surname,
            'has_role' => $userInfo->role ? 'Yes' : 'No',
            'has_department' => $userInfo->department ? 'Yes' : 'No',
            'role' => $userInfo->role ? $userInfo->role->role : 'None', // Changed 'name' to 'role'
            'department' => $userInfo->department ? $userInfo->department->department : 'None', // Changed 'name' to 'department'
        ]);
        
        return $userInfo;
    }
    
    /**
     * Check if the currently authenticated user is authorized to modify an announcement.
     *
     * @param Announcement $announcement
     * @return bool
     */
    protected function isAuthorized(Announcement $announcement)
    {
        $userId = $this->getCurrentUserId();
        return $userId && $userId == $announcement->user_id;
    }

    /**
     * Debug the authentication flow and return detailed information.
     *
     * @return array
     */
    protected function debugAuthFlow()
    {
        // Initialize debug info array
        $debug = [
            'auth' => [
                'status' => Auth::check(),
                'id' => Auth::id(),
                'guard' => Auth::getDefaultDriver(),
            ],
            'login_table' => [
                'found' => false,
                'user_id' => null,
            ],
            'user_information' => [
                'found' => false,
                'details' => [],
            ],
            'role' => [
                'found' => false,
                'details' => [],
            ],
            'department' => [
                'found' => false,
                'details' => [],
            ],
        ];
        
        // Get login info
        $loginId = Auth::id();
        $loginRecord = DB::table('login')->where('user_login_id', $loginId)->first();
        
        if ($loginRecord) {
            $debug['login_table']['found'] = true;
            $debug['login_table']['user_id'] = $loginRecord->user_id;
            
            // Get user information
            $userInfo = UserInformation::with(['department', 'role'])->find($loginRecord->user_id);
            
            if ($userInfo) {
                $debug['user_information']['found'] = true;
                $debug['user_information']['details'] = [
                    'user_id' => $userInfo->user_id,
                    'name' => $userInfo->name ?? 'Unknown',
                    // Add other relevant user information fields
                ];
                
                // Get role information
                if ($userInfo->role) {
                    $debug['role']['found'] = true;
                    $debug['role']['details'] = [
                        'id' => $userInfo->role->id,
                        'name' => $userInfo->role->role,
                    ];
                }
                
                // Get department information
                if ($userInfo->department) {
                    $debug['department']['found'] = true;
                    $debug['department']['details'] = [
                        'id' => $userInfo->department->id,
                        'name' => $userInfo->department->department,
                    ];
                }
            }
        }
        
        return $debug;
    }

    /**
     * Display a listing of the announcements.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Change from get() to paginate()
        $announcements = Announcement::latest()->paginate(10); // Show 10 items per page
        return view('announcements', compact('announcements'));
    }

    /**
     * Show the form for creating a new announcement.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Get user information for the author display
        $userInfo = $this->getCurrentUserInfo();
        
        return view('announcements.create', compact('userInfo'));
    }

    /**
     * Store a newly created announcement in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            Log::info('Announcement store method started', [
                'request_data' => $request->all()
            ]);
            
            // Validate the form data
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|min:10',
                'category' => 'required|string|max:50',
            ]);
            
            Log::info('Announcement form validated successfully', [
                'title' => $validated['title'],
                'category' => $validated['category'],
                'content_length' => strlen($validated['content'])
            ]);
            
            // Get current user ID
            $userId = $this->getCurrentUserId();
            Log::info('Current user ID', ['user_id' => $userId]);
            
            if (!$userId) {
                Log::error('Failed to determine user ID - aborting announcement creation');
                return redirect()->back()
                    ->with('error', 'Could not determine your user identity. Please contact support.');
            }
            
            // Get user info for author details
            $userInfo = $this->getCurrentUserInfo();
            Log::info('User info for announcement', [
                'user_info_retrieved' => $userInfo ? true : false
            ]);
            
            // Get department and role information
            $departmentName = "General Department";
            $roleName = "Staff Member";
            $authorName = "Unknown User";
            
            // Check if user info was retrieved
            if ($userInfo) {
                // Get author name
                if ($userInfo->user_name) {
                    $authorName = $userInfo->user_name;
                    if ($userInfo->user_surname) {
                        $authorName .= ' ' . $userInfo->user_surname;
                    }
                }
                
                // Get role information
                if ($userInfo->role) {
                    $roleName = $userInfo->role->role; // Access 'role' column, not 'name'
                    Log::info('Role found for user', [
                        'role_id' => $userInfo->role->role_id,
                        'role' => $roleName
                    ]);
                } else {
                    Log::warning('No role found for user', ['user_id' => $userId]);
                    
                    // Fallback: Try direct query to get role info
                    $roleInfo = DB::table('user_information')
                        ->join('roles', 'user_information.role_id', '=', 'roles.role_id') // Changed 'id' to 'role_id'
                        ->where('user_information.user_id', $userId)
                        ->select('roles.role') // Changed 'name' to 'role'
                        ->first();
                    
                    if ($roleInfo) {
                        $roleName = $roleInfo->role; // Changed 'name' to 'role'
                        Log::info('Role found via direct query', ['role' => $roleName]);
                    }
                }
                
                // Get department information
                if ($userInfo->department) {
                    $departmentName = $userInfo->department->department; // Access 'department' column, not 'name'
                    Log::info('Department found for user', [
                        'department_id' => $userInfo->department->department_id,
                        'department' => $departmentName
                    ]);
                } else {
                    Log::warning('No department found for user', ['user_id' => $userId]);
                    
                    // Fallback: Try direct query to get department info
                    $deptInfo = DB::table('user_information')
                        ->join('departments', 'user_information.department_id', '=', 'departments.department_id') // Changed 'id' to 'department_id'
                        ->where('user_information.user_id', $userId)
                        ->select('departments.department') // Changed 'name' to 'department'
                        ->first();
                    
                    if ($deptInfo) {
                        $departmentName = $deptInfo->department; // Changed 'name' to 'department'
                        Log::info('Department found via direct query', ['department' => $departmentName]);
                    }
                }
            } else {
                Log::warning('User info not found for announcement author', ['user_id' => $userId]);
            }
            
            // Create and save the announcement
            $announcement = new Announcement();
            $announcement->title = $validated['title'];
            $announcement->content = $validated['content'];
            $announcement->category = $validated['category'];
            $announcement->user_id = $userId;
            $announcement->author_name = $authorName;
            $announcement->author_job_title = $roleName;
            $announcement->author_department = $departmentName;
            
            // Log announcement data before saving
            Log::info('Attempting to save announcement', [
                'announcement_data' => [
                    'title' => $announcement->title,
                    'category' => $announcement->category,
                    'user_id' => $announcement->user_id,
                    'author_name' => $announcement->author_name,
                    'author_job_title' => $announcement->author_job_title,
                    'author_department' => $announcement->author_department,
                    'content_length' => strlen($announcement->content)
                ]
            ]);
            
            // Try to save and log the SQL query
            DB::enableQueryLog();
            $saved = $announcement->save();
            $queries = DB::getQueryLog();
            
            Log::info('Announcement save attempt', [
                'save_result' => $saved ? 'success' : 'failed',
                'save_queries' => $queries
            ]);
            
            if (!$saved) {
                Log::error('Failed to save announcement');
                return redirect()->back()
                    ->with('error', 'Failed to save announcement. Please try again.');
            }
            
            Log::info('Announcement created successfully', [
                'announcement_id' => $announcement->id
            ]);
            
            return redirect()->route('announcements')
                ->with('success', 'Announcement created successfully!');
                
        } catch (\Exception $e) {
            Log::error('Exception in announcement creation', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Error creating announcement: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified announcement.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(string $id)
    {
        $announcement = Announcement::findOrFail($id);
        
        // Check if user is authorized to edit this announcement
        if (!$this->isAuthorized($announcement)) {
            return redirect()->route('announcements')
                ->with('error', 'You are not authorized to edit this announcement.');
        }
        
        return view('announcements.edit', compact('announcement'));
    }

    /**
     * Update the specified announcement in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'category' => 'sometimes|nullable|string|max:50',
        ]);
        
        // Find the announcement
        $announcement = Announcement::findOrFail($id);
        
        // Check if user is authorized to edit this announcement
        if (!$this->isAuthorized($announcement)) {
            return redirect()->route('announcements')
                ->with('error', 'You are not authorized to edit this announcement.');
        }
        
        // Update only the editable fields
        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'] ?? $announcement->category,
        ]);
        
        return redirect()->route('announcements')
            ->with('success', 'Announcement updated successfully!');
    }

    /**
     * Show the confirmation page for deleting an announcement.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function confirmDelete($id)
    {
        $announcement = Announcement::findOrFail($id);
        
        // Check if user is authorized to delete this announcement
        if (!$this->isAuthorized($announcement)) {
            return redirect()->route('announcements')
                ->with('error', 'You are not authorized to delete this announcement.');
        }
        
        return view('announcements.delete', compact('announcement'));
    }

    /**
     * Remove the specified announcement from storage.
     *
     * @param  \App\Models\Announcement  $announcement
     * @return \Illuminate\Http\Response
     */
    public function destroy(Announcement $announcement)
    {
        // Check if user is authorized to delete this announcement
        if (!$this->isAuthorized($announcement)) {
            return redirect()->route('announcements')
                ->with('error', 'You are not authorized to delete this announcement.');
        }
        
        $announcement->delete();
        
        return redirect()->route('announcements')
            ->with('success', 'Announcement deleted successfully.');
    }
}


