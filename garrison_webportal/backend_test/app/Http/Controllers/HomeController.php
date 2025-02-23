<?php
// filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/app/Http/Controllers/HomeController.php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('home');
    }
}