<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessLead;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class BusinessLeadController extends Controller
{
    public function index()
    {
        $leads = BusinessLead::with('user')->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $leads
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_email' => 'nullable|email',
            'business_phone' => 'nullable|string|max:20',
            'business_type' => 'required|string',
            'website_url' => 'nullable|url',
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
}
