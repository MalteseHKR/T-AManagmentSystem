@extends('layouts.app')

@section('title', 'Images - Garrison Time and Attendance System')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Images</h1>
        <a href="{{ route('attendance') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Attendance
        </a>
    </div>

    @if(isset($error))
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <h2>Available Images</h2>
    
    @if(count($files) > 0)
        <div class="row">
            @foreach($files as $file)
                @php
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                @endphp
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        @if($isImage)
                            <img src="{{ route('images.serve', ['filename' => $file]) }}" class="card-img-top" alt="{{ $file }}">
                        @else
                            <div class="card-img-top bg-light text-center py-5">
                                <i class="fas fa-file fa-4x text-secondary"></i>
                            </div>
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">{{ basename($file) }}</h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    Type: {{ strtoupper($extension) }} file
                                </small>
                            </p>
                            <a href="{{ route('images.serve', ['filename' => $file]) }}" class="btn btn-primary" target="_blank">View</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-info">
            No images found on the SFTP server.
        </div>
    @endif
</div>
@endsection