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
        
        // Look up the user_id in the login table
        $loginRecord = DB::table('login')->where('user_login_id', $loginId)->first();
        
        if (!$loginRecord) {
            Log::warning("No login record found for login_id: $loginId");
            return null;
        }
        
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
        
        if (!$userId) {
            return null;
        }
        
        return UserInformation::find($userId);
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
        // Validate form data
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|max:50',
        ]);
        
        // Get the current user's actual user_id (not login_id)
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            return redirect()->back()
                ->with('error', 'Could not determine your user identity. Please contact support.');
        }
        
        // Get user information for author details
        $userInfo = $this->getCurrentUserInfo();
        
        // Create the announcement
        $announcement = new Announcement();
        $announcement->title = $validated['title'];
        $announcement->content = $validated['content'];
        $announcement->category = $validated['category'];
        $announcement->user_id = $userId;
        
        // Set author information
        if ($userInfo) {
            $announcement->author_name = $userInfo->user_name . ' ' . $userInfo->user_surname;
            $announcement->author_job_title = $userInfo->user_title;
            $announcement->author_department = $userInfo->user_department;
            
            Log::info("Creating announcement with author info", [
                'user_id' => $userId,
                'author_name' => $announcement->author_name,
                'author_job_title' => $announcement->author_job_title,
                'author_department' => $announcement->author_department
            ]);
        } else {
            $announcement->author_name = Auth::user()->name;
            Log::warning("Creating announcement without full author info. Using login name instead", [
                'login_id' => Auth::id(),
                'user_id' => $userId,
                'name' => Auth::user()->name
            ]);
        }
        
        // Save the announcement
        $announcement->save();
        
        return redirect()->route('announcements')
            ->with('success', 'Announcement created successfully!');
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
            'content' => 'required|string',
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


