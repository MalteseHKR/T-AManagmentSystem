@extends('layouts.app')

@section('title', 'Announcements - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Announcements</h1>
        <div>
            <a href="dashboard" class="btn btn-secondary me-2">Back</a>
            <a href="{{ route('announcements.create') }}" class="btn btn-primary">Create an Announcement</a>
        </div>
    </div>

    @if($announcements->isEmpty())
        <div class="alert alert-info">
            No announcements available at this time.
        </div>
    @else
        @foreach($announcements as $announcement)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $announcement->title }}</h5>
                    <small class="text-muted">{{ $announcement->created_at->format('d M Y, H:i') }}</small>
                </div>
                <div class="card-body">
                    <p class="card-text">{{ $announcement->content }}</p>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection