<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserInformation;

class UserInformationController extends Controller
{
    public function index()
    {
        $userInformation = UserInformation::all();
        return view('user_information.index', compact('userInformation'));
    }
}
