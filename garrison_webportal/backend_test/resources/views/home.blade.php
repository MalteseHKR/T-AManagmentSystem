@extends('app')

@section('content')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Garrison Time and Attendance System</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="container text-center">
            <img src="{{ asset('garrison.svg') }}" alt="Garrison Logo" class="logo">
            <h1 class="mt-4">Welcome to Garrison Time and Attendance System</h1>
            <a href="{{ route('login') }}" class="btn btn-primary mt-4">Login</a>
        </div>
    </body>
</html>
@endsection
