<?php

namespace App\Http\Controllers;

use App\Models\TestResult;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Panel;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller {}