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

class DashboardController extends Controller
{
    public function index()
    {
        $user_name = session()->get('username');
        $lab_id = session()->get('lab_id');

        if ($lab_id != 1) {
            abort(403, 'Unauthorized');
        }

        // Get summary statistics with caching (PERFORMANCE OPTIMIZATION)
        // Cache for 5 minutes to reduce database load
        $stats = Cache::remember('dashboard_stats', 300, function () {
            return [
                'total_labs' => Lab::count(),
                'total_tests' => TestResult::count(),
                'total_patients' => Patient::count(),
                'total_doctors' => Doctor::count(),
                'total_panels' => Panel::count(),
                'tests_this_month' => TestResult::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
                'completed_tests' => TestResult::where('is_completed', 1)->count(),
                'pending_tests' => TestResult::where('is_completed', 0)->count(),
            ];
        });

        // Get recent test results
        $recent_tests = TestResult::with(['patient', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // Get lab information
        $labs = Lab::all();

        return view('dashboard', compact('user_name', 'lab_id', 'stats', 'recent_tests', 'labs'));
    }
}