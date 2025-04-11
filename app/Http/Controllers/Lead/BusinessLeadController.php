<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessLead;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class BusinessLeadController extends Controller
{
    public function index(Request $request, $userId)
    {
        $authUser = User::findOrFail($userId);

        $query = BusinessLead::with('user');

        // Role-based access logic
        if ($authUser->type === 'client' && $authUser->is_subscribe == 0) {
            $query->select('business_name', 'business_type', 'status', 'created_at', 'updated_at', 'user_id');
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%$search%")
                    ->orWhere('business_email', 'like', "%$search%")
                    ->orWhere('business_phone', 'like', "%$search%");
            });
        }

        // Filter: Business Type
        if ($type = $request->input('business_type')) {
            $query->where('business_type', $type);
        }

        // Filter: Status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter: Location
        if ($location = $request->input('location')) {
            $query->where('location', $location);
        }

        // Filter: Date Range
        if ($from = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Always order by latest
        $query->orderByDesc('created_at');

        // Pagination
        $perPage = $request->input('limit');
        $currentPage = $request->input('page');

        if ($perPage && $currentPage) {
            $leads = $query->paginate($perPage, ['*'], 'page', $currentPage);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Business leads retrieved successfully',
                'data' => $leads->items(),
                'pagination' => [
                    'total_rows' => $leads->total(),
                    'current_page' => $leads->currentPage(),
                    'per_page' => $leads->perPage(),
                    'total_pages' => $leads->lastPage(),
                ],
            ]);
        } else {
            $leads = $query->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Business leads retrieved successfully',
                'data' => $leads
            ]);
        }
    }





    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255|unique:business_leads,business_name',
            'business_email' => 'nullable|email|unique:business_leads,business_email',
            'business_phone' => 'nullable|string|max:20|unique:business_leads,business_phone',
            'business_type' => 'required|string',
            'website_url' => 'nullable|url|unique:business_leads,website_url',
            'location' => 'nullable|string|max:255',
            'source_of_data' => 'nullable|string|max:255',
            'status' => 'required|string',
            'note' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $lead = BusinessLead::create($request->all());

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Business lead created successfully',
            'data' => $lead
        ]);
    }

    public function show($id)
    {
        $lead = BusinessLead::with('user')->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Lead not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $lead
        ]);
    }

    public function update(Request $request, $id)
    {
        $lead = BusinessLead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Lead not found'
            ]);
        }

        $lead->update($request->all());

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Business lead updated successfully',
            'data' => $lead
        ]);
    }

    public function destroy($id)
    {
        $lead = BusinessLead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Lead not found'
            ]);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Lead deleted successfully'
        ]);
    }

    public function totalLeadCount()
    {
        $count = BusinessLead::count();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Total business leads count retrieved successfully',
            'total_leads' => $count
        ]);
    }



    public function userLeadCount($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found'
            ], 404);
        }

        $count = BusinessLead::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Lead count for user ID: $userId",
            'user_id' => $userId,
            'lead_count' => $count
        ]);
    }
}
