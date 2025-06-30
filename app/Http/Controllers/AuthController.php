<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Rejestracja nowego użytkownika.
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => 'user'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'userData' => $this->formatUserData($user),
            'accessToken' => $token,
            'tokenType' => 'Bearer'
        ], 201);
    }

    /**
     * Logowanie użytkownika.
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt(['email' => $fields['email'], 'password' => $fields['password']])) {
            return response()->json(['message' => 'Nieprawidłowe dane logowania'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'userData' => $this->formatUserData($user),
            'accessToken' => $token,
            'tokenType' => 'Bearer'
        ]);
    }

    /**
     * Pobierz dane zalogowanego użytkownika.
     */
    public function user(Request $request)
    {
        return response()->json($this->formatUserData($request->user()));
    }

    /**
     * Wylogowanie użytkownika (usunięcie wszystkich tokenów).
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Pomyślnie wylogowano']);
    }

    /**
     * Helper do formatowania userData z rolą i abilities
     */
    private function formatUserData($user)
    {
        $abilities = match ($user->role ?? 'user') {
            'admin' => [
                ['action' => 'manage', 'subject' => 'all'],
            ],
            'manager' => [
                ['action' => 'read', 'subject' => 'all'],
                ['action' => 'manage', 'subject' => 'Product'],
            ],
            default => [
                ['action' => 'read', 'subject' => 'Dashboard'],
            ],
        };

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'user',
            'ability' => $abilities, // DLA CASL!
        ];
    }
}
