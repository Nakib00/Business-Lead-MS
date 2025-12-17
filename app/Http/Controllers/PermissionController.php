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

            // Toggle the status
            // Assuming status is stored in a way that allows boolean toggling (tinyint 0/1 or similar)
            // Explicitly casting to bool for safety before toggling if it's a string 'true'/'false'
            $currentStatus = filter_var($permission->status, FILTER_VALIDATE_BOOLEAN);
            $permission->status = !$currentStatus;

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
