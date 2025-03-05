@extends('layouts.auth')

@section('title', 'Post Announcement - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <h1 class="mb-4">Post Announcement</h1>
    <p>Post company-wide announcements and keep employees informed about important updates.</p>

    <a href="{{ route('announcements') }}" class="btn btn-secondary mb-4">Back to Announcements</a>

    <form method="POST" action="{{ route('announcements.store') }}">
        @csrf
        <div class="form-group mb-3">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" class="form-control" placeholder="Enter announcement title" required>
        </div>
        <div class="form-group mb-3">
            <label for="content">Content</label>
            <textarea name="content" id="content" class="form-control" rows="5" placeholder="Enter announcement content" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Post Announcement</button>
    </form>
</div>
@endsection