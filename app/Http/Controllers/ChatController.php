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

        // Admin can chat with their staff, members, clients
        // Staff/Member/Client can chat with their admin and peers
        $users = User::where('id', '!=', $user->id)
            ->where('reg_user_id', $user->reg_user_id)
            ->orWhere('id', $user->reg_user_id)
            ->select('id', 'name', 'email', 'type', 'profile_image')
            ->get();

        return response()->json($users);
    }
}
