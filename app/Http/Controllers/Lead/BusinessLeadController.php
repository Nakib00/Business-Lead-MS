<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessLead;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class BusinessLeadController extends Controller
{
    public function allBusinessLeads(Request $request)
    {
        $query = BusinessLead::with('user');

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

        // Filter by specific user
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
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
                'message' => 'All business leads retrieved successfully',
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
                'message' => 'All business leads retrieved successfully',
                'data' => $leads
            ]);
        }
    }


    // shwo all leads for admin
    public function showLeadAdmin(Request $request, $userId)
    {
        $authUser = User::findOrFail($userId);

        $query = BusinessLead::with('user');

        // Admin can see all leads created by them OR by users they registered
        if ($authUser->type === 'admin') {
            $query->where(function ($q) use ($authUser) {
                // Get leads created by this admin
                $q->where('user_id', $authUser->id)
                    // OR get leads created by users registered by this admin
                    ->orWhereHas('user', function ($subQuery) use ($authUser) {
                        $subQuery->where('reg_user_id', $authUser->id);
                    });
            });
        } else {
            // Regular users can only see their own leads
            $query->where('user_id', $authUser->id);
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

    // shwo leads to created user (mamber, leader)
    public function createorLeads(Request $request, $userId)
    {
        // Verify user exists
        $user = User::findOrFail($userId);

        $query = BusinessLead::with('user')
            ->where('user_id', $userId);

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
                'message' => 'User business leads retrieved successfully',
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
                'message' => 'User business leads retrieved successfully',
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
            'note' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->all();
            $data['status'] = 'new';

            $lead = BusinessLead::create($data);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Business lead created successfully',
                'data' => $lead,
            ]);
        } catch (\Exception $e) {
            \Log::error('BusinessLead Creation Failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong while creating the business lead',
            ], 500);
        }
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

        $data = $request->only([
            'business_name',
            'business_email',
            'business_phone',
            'business_type',
            'website_url',
            'location',
            'source_of_data',
            'note'
        ]);

        $lead->update($data);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Business lead updated successfully',
            'data' => $lead
        ]);
    }

    // update lead status
    public function updateStatus(Request $request, $id)
    {
        $lead = BusinessLead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Lead not found'
            ]);
        }

        // Validate input
        $request->validate([
            'status' => 'required|string|max:255'
        ]);

        // Update only the status
        $lead->status = $request->status;
        $lead->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Status updated successfully',
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

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->user_id;

        $file = $request->file('file');
        $data = [];

        if ($file->getClientOriginalExtension() === 'csv') {
            $data = array_map('str_getcsv', file($file));
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            unset($data[0]); // Remove headers
        } else {
            $data = Excel::toArray([], $file)[0];
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            unset($data[0]); // Remove headers
        }

        $inserted = 0;
        foreach ($data as $row) {
            $row = array_combine($headers, $row);
            $row['user_id'] = $userId;

            // Set default 'status' to 'new' if not present
            if (!isset($row['status']) || empty($row['status'])) {
                $row['status'] = 'new';
            }

            $validator = Validator::make($row, [
                'business_name' => 'required|string|max:255|unique:business_leads,business_name',
                'business_email' => 'nullable|email|unique:business_leads,business_email',
                'business_phone' => 'nullable|string|max:20|unique:business_leads,business_phone',
                'business_type' => 'required|string',
                'website_url' => 'nullable|url|unique:business_leads,website_url',
                'location' => 'nullable|string|max:255',
                'source_of_data' => 'nullable|string|max:255',
                'status' => 'string', // Changed from 'required|string'
                'note' => 'nullable|string',
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                // Optional: log or collect errors per row
                continue;
            }

            BusinessLead::create($row);
            $inserted++;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "$inserted leads uploaded successfully."
        ]);
    }
}
