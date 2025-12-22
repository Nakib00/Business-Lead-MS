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

    public function update(Request $request)
    {
        try {
            $input = $request->all();

            // Validate that we received an array
            if (!is_array($input)) {
                return $this->errorResponse('Invalid input format. Expected an array of permissions.', 422);
            }

            $updatedPermissions = [];
            $errors = [];

            foreach ($input as $item) {
                if (!isset($item['id']) || !isset($item['status'])) {
                    continue; // Skip invalid items
                }

                try {
                    $permission = Permission::findOrFail($item['id']);

                    // Update status
                    $permission->status = filter_var($item['status'], FILTER_VALIDATE_BOOLEAN);
                    $permission->save();

                    $updatedPermissions[] = [
                        'id' => $permission->id,
                        'status' => $permission->status
                    ];
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $errors[] = "Permission with ID {$item['id']} not found.";
                }
            }

            return $this->successResponse([
                'updated_count' => count($updatedPermissions),
                'updated' => $updatedPermissions,
                'errors' => $errors // Optional: return errors for specific IDs if needed
            ], 'Permissions updated successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
