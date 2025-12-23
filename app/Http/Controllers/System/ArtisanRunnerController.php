<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;

class ArtisanRunnerController extends Controller
{
    use ApiResponseTrait;

    protected $allowedCommands = [
        'optimize:clear',
        'config:cache',
        'route:cache',
        'view:cache',
        'migrate',
        'storage:link',
        'cache:clear'
    ];

    public function run(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse('Login required');
        }

        // Ideally, check for admin privileges here
        // if ($user->type !== 'admin') { return $this->forbiddenResponse('Admins only'); }

        $command = $request->input('command');

        if (!in_array($command, $this->allowedCommands)) {
            return $this->errorResponse('Command not allowed', null, 403);
        }

        try {
            Artisan::call($command);
            $output = Artisan::output();

            return $this->successResponse([
                'command' => $command,
                'output' => $output
            ], 'Command executed successfully');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Command execution failed', $e->getMessage());
        }
    }
}
