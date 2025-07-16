<?php

namespace App\Http\Controllers;

use App\Http\Requests\InnoquestResultRequest;
use App\Http\Requests\StorePatientResultRequest;
use App\Models\DeliveryFile;
use App\Models\DeliveryFileHistory;
use App\Models\DoctorCode;
use App\Models\Panel;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelMetadata;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultItem;
use App\Models\TestResultReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller {}
