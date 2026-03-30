<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CrmReferenceData;

class ReferenceDataController extends Controller
{
    public function index()
    {
        return response()->json([
            'branches' => CrmReferenceData::branchOptions(),
            'lead_sources' => CrmReferenceData::options('lead_sources'),
            'request_types' => CrmReferenceData::options('request_types'),
            'payment_statuses' => CrmReferenceData::options('payment_statuses'),
        ]);
    }
}