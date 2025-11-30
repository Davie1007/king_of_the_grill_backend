<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Notifications\SystemSecurityNotification;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username'=>'required|string|unique:users,username',
            'email'=>'nullable|email|unique:users,email',
            'password'=>'required|string|min:6',
            'role'=>['required', Rule::in(['Owner','Manager','Cashier','Seller'])],
            'branch_id'=>'nullable|exists:branches,id',
        ]);

        $user = User::create([
            'username'=>$data['username'],
            'email'=>$data['email'] ?? null,
            'password'=>Hash::make($data['password']),
            'role'=>$data['role'],
            'branch_id'=>$data['branch_id'] ?? null,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);
    
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;
    
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'photo' => $user->photo,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                    ] : null,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        }
    
        $owner = User::where('role', 'Owner')->first();
        $owner->notify(new SystemSecurityNotification('Failed login attempt detected.'));
        return response()->json(['detail' => 'Invalid credentials'], 401);
    }

    public function token(Request $request)
    {
        return $this->login($request);
    }

    /**
     * Logout (delete tokens).
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
    /**
     * Get current user.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}



