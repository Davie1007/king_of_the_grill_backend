<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get the authenticated user's profile data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        $user = Auth::user()->load('branch'); // Load branch relationship
        return response()->json([
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'branch' => $user->branch ? ['name' => $user->branch->name] : null,
            'photo' => $user->photo,
        ], 200);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'username' => $request->username,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'Profile updated successfully'], 200);
    }

    /**
     * Update the authenticated user's profile photo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        $user = Auth::user();

        // Handle file upload
        if ($request->hasFile('photo')) {
            // Delete old photo if it exists
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            // Store new photo
            $path = $request->file('photo')->store('users', 'public');

            // Update user’s photo field
            $user->update(['photo' => $path]);

            return response()->json(['photo' => $path], 200);
        }

        return response()->json(['message' => 'No photo uploaded'], 400);
    }
}