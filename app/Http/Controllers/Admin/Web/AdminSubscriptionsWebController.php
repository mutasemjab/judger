<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class AdminSubscriptionsWebController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = UserSubscription::with('user', 'plan')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->plan_id, fn($q, $v) => $q->where('subscription_plan_id', $v))
            ->latest()
            ->paginate(20);

        $plans = SubscriptionPlan::all();

        return view('admin.subscriptions.index', compact('subscriptions', 'plans'));
    }
}
