<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class AdminPlansWebController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::all();

        return view('admin.plans.index', compact('plans'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $request->validate([
            'description' => 'nullable|string|max:500',
            'price'       => 'nullable|numeric|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $plan->update([
            'description' => $request->description,
            'price'       => $request->price,
            'is_active'   => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('messages.updated_success'));
    }
}
