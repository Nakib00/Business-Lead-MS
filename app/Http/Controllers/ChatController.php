<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Models\User;

class ChatController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase
    ) {}

    /**
     * Get a Firebase auth token for the current user.
     * The React app calls this on login.
     */
    public function getFirebaseToken(Request $request)
    {
        $user = $request->user();

        $claims = [
            'laravelUserType' => $user->type,
            'name'            => $user->name,
        ];

        $token = $this->firebase->createCustomToken($user->id, $claims);

        return response()->json(['firebase_token' => $token]);
    }

    /**
     * Start or get existing conversation between two users.
     */
    public function startConversation(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
        ]);

        $currentUser = $request->user();
        $recipientId = $request->recipient_id;

        // You might want to check if a conversation already exists
        // between these two users in your MySQL DB

        $conversationId = $this->firebase->createConversation(
            [$currentUser->id, $recipientId],
            'direct'
        );

        return response()->json([
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * Get list of users the current user can chat with.
     */
    public function chatableUsers(Request $request)
    {
        $user = $request->user();

        // Determine the "Organization ID" (the Admin's ID)
        // If reg_user_id is null, this user is the Admin.
        // If reg_user_id is set, that is the Admin's ID.
        $organizationId = $user->reg_user_id ?? $user->id;

        // Fetch users who belong to the same organization:
        // 1. Users whose reg_user_id matches our organizationId (Siblings/Children)
        // 2. The Admin user themselves (id == organizationId)
        // 3. Exclude the current user
        $users = User::where('id', '!=', $user->id)
            ->where(function ($query) use ($organizationId) {
                $query->where('reg_user_id', $organizationId)
                    ->orWhere('id', $organizationId);
            })
            ->select('id', 'name', 'email', 'type', 'profile_image', 'reg_user_id')
            ->get();

        return response()->json($users);
    }
}
