<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\UserInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    public function index()
    {
        // Change from get() to paginate()
        $announcements = Announcement::latest()->paginate(10); // Show 10 items per page
        return view('announcements', compact('announcements'));
    }

    public function create()
    {
        return view('announcements.create');
    }

    /**
     * Store a newly created announcement in storage.
     */
    public function store(Request $request)
    {
        // Validate form input
        $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'category' => 'nullable|max:50',
        ]);
        
        // Get user ID directly from Auth facade
        $userId = Auth::id();
        
        // Log the user ID for debugging
        Log::info('Creating announcement with user_id: ' . $userId);
        
        // Get user information from user_information table using correct relationship
        $userInfo = UserInformation::where('user_id', $userId)->first();
        
        // Log whether we found user info
        if ($userInfo) {
            Log::info('Found user info: ' . $userInfo->user_name);
        } else {
            Log::warning('No user information found for user ID: ' . $userId);
        }
        
        // Create a new announcement instance
        $announcement = new Announcement();
        
        // Set basic announcement properties
        $announcement->title = $request->title;
        $announcement->content = $request->content;
        $announcement->category = $request->category;
        $announcement->user_id = $userId;
        
        // Set author name - use user_name from UserInformation or fall back to Auth::user()->name
        $announcement->author_name = $userInfo ? 
            $userInfo->user_name . ' ' . $userInfo->user_surname : 
            Auth::user()->name;
        
        // Set author job title - map from user_title field
        $announcement->author_job_title = $userInfo ? $userInfo->user_title : null;
        
        // Set author department - map from user_department field
        $announcement->author_department = $userInfo ? $userInfo->user_department : null;
        
        // Log what we're about to save
        Log::info('Saving announcement with data:', [
            'user_id' => $announcement->user_id,
            'author_name' => $announcement->author_name,
            'author_job_title' => $announcement->author_job_title,
            'author_department' => $announcement->author_department,
        ]);
        
        // Save the announcement
        $announcement->save();
        
        // Show success message
        return redirect()->route('announcements')
            ->with('success', 'Announcement created successfully!');
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
