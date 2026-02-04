<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Database;

class FirebaseService
{
    protected FirebaseAuth $auth;
    protected Database $database;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
            ->withDatabaseUri(env('FIREBASE_DATABASE_URL'));

        $this->auth = $factory->createAuth();
        $this->database = $factory->createDatabase();
    }

    /**
     * Generate a Firebase custom token for a Laravel user.
     * React will use this to authenticate with Firebase.
     */
    public function createCustomToken(int $userId, array $claims = []): string
    {
        $token = $this->auth->createCustomToken((string) $userId, $claims);
        return $token->toString();
    }

    /**
     * Create a conversation between users in Firebase.
     */
    public function createConversation(array $userIds, string $type = 'direct'): string
    {
        $conversationId = uniqid('conv_', true);

        // Set conversation metadata
        $this->database->getReference("conversations/{$conversationId}/meta")->set([
            'type'      => $type, // 'direct' or 'group'
            'createdAt' => now()->timestamp * 1000,
        ]);

        // Set members
        $members = [];
        foreach ($userIds as $id) {
            $members[(string) $id] = true;
        }
        $this->database->getReference("conversationMembers/{$conversationId}")->set($members);

        // Add to each user's conversation list
        foreach ($userIds as $id) {
            $this->database->getReference("userConversations/{$id}/{$conversationId}")->set([
                'lastMessage' => '',
                'updatedAt'   => now()->timestamp * 1000,
            ]);
        }

        return $conversationId;
    }
}
