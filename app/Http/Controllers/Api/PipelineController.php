<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;

class PipelineController extends Controller
{
    public function index()
    {
        return response()->json(Pipeline::where('is_active', true)->get());
    }

    public function stages(Pipeline $pipeline)
    {
        return response()->json($pipeline->stages()->orderBy('sort_order')->get());
    }
}
