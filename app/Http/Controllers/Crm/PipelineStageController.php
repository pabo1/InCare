<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\PipelineStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineStageController extends Controller
{
    public function update(Request $request, PipelineStage $stage): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'is_final' => ['required', 'boolean'],
            'is_fail' => ['required', 'boolean', Rule::prohibitedIf(fn () => ! $request->boolean('is_final'))],
        ]);

        if (! $data['is_final']) {
            $data['is_fail'] = false;
        }

        $stage->update($data);

        return back();
    }
}
