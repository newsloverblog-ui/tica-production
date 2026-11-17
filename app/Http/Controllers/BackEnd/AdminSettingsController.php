<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return view('backend.settings.index');
    }
}
