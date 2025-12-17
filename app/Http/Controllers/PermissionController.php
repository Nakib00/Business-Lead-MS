<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Traits\ApiResponseTrait;

class PermissionController extends Controller
{
    use ApiResponseTrait;

    public function update(Request $request, $permissionId)
    {
        try {
            $permission = Permission::findOrFail($permissionId);

            // Validate the status input
            $validator = Validator::make($request->all(), [
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation error', $validator->errors()->first(), 422);
            }

            $permission->status = $request->input('status');
            $permission->save();

            return $this->successResponse([
                'id' => $permission->id,
                'feature' => $permission->feature,
                'api_method' => $permission->api_method,
                'status' => (bool)$permission->status
            ], 'Permission updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Permission not found', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
