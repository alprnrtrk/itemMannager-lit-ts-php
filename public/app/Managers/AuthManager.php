<?php
// public/app/Managers/AuthManager.php

namespace App\Managers;

use App\Config\Config; // Import the Config class to get the secret password

class AuthManager
{
    /**
     * Authenticates an admin user based on a provided password.
     * @param string $password The password provided by the user.
     * @return array An associative array indicating authentication status and a message.
     */
    public function authenticate(string $password): array
    {
        // Compare the provided password with the secret password defined in Config
        if ($password === Config::ADMIN_PASSWORD_SECRET) {
            return ['authenticated' => true, 'message' => 'Authentication successful'];
        } else {
            return ['authenticated' => false, 'message' => 'Invalid password'];
        }
    }
}