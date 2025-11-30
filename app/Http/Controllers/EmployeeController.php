<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeEmployeeMail;
use App\Models\User;
use App\Models\Employee;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class EmployeeController extends Controller
{
    /**
     * Display a listing of employees for a specific branch.
     */
    public function index($branchId)
    {
        $employees = Employee::with('user')->where('branch_id', $branchId)->get();
    return response()->json($employees);
    }

    /**
     * Store a newly created employee in a branch.
     */
    public function store(Request $request, $branchId)
    {

        \Log::info('Incoming request data:', $request->all());
        \Log::info('Incoming request files:', $request->allFiles());

        $branch = Branch::findOrFail($branchId);

        try {
            $validated = $request->validate([
                'username'   => 'required|string|max:255|unique:users,username',
                'email'      => 'required|email|unique:users,email',
                'password'   => 'nullable|string|min:6',
                'idNumber'   => 'required|string|max:100|unique:employees,idNumber',
                'position'   => 'required|string|max:100',
                'experience' => 'nullable|string|max:255',
                'role'       => 'nullable|string|max:100',
                // remove "image" rule → allows requests without a file
                'photo' => 'sometimes|file|mimes:jpg,jpeg,png,gif,webp,heic|max:4096',
                'name'   => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        $password = $validated['password'] ?? Str::random(10);

        // Handle photo (optional)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users', 'public');
        }

        $user = User::create([
            'username'  => $validated['username'],
            'email'     => $validated['email'],
            'password'  => bcrypt($password),
            'branch_id' => $branchId,
            'role'      => 'employee',
            'photo'     => $photoPath,
        ]);

        $employee = $user->employee()->create([
            'idNumber'   => $validated['idNumber'],
            'position'   => $validated['position'],
            'experience' => $validated['experience'] ?? null,
            'branch_id'  => $branchId,
            'suspended'  => false,
            'name'       => $validated['name'] ?? null,
            'status'     => $validated['status'] ?? 'active',
        ]);

        Mail::to($user->email)->send(new WelcomeEmployeeMail($user, $password));

        return response()->json([
            'message'  => 'Employee added successfully',
            'user'     => $user,
            'employee' => $employee,
        ], 201);
    }

    public function update(Request $request, $branchId, $employeeId)
    {
        $employee = Employee::with('user')->where('branch_id', $branchId)->findOrFail($employeeId);

        $validatedUser = $request->validate([
            'username' => 'sometimes|string|max:255|unique:users,username,' . $employee->user->id,
            'email'    => 'sometimes|email|unique:users,email,' . $employee->user->id,
            'photo'    => 'nullable|image|max:2048',
        ]);
        
        $validatedEmployee = $request->validate([
            'position'   => 'sometimes|string|max:100',
            'experience' => 'nullable|string|max:255',
            'role'       => 'nullable|string|max:100',
            // add these:
            'idNumber'   => 'sometimes|string|max:100|unique:employees,idNumber,' . $employee->id,
            'name'       => 'nullable|string|max:255',
            'status'     => 'nullable|string|in:active,inactive',
        ]);
        
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users', 'public');
            $validatedUser['photo'] = $photoPath;
        }

        $employee->user->update($validatedUser);
        $employee->update($validatedEmployee);

        return response()->json(['message' => 'Employee updated successfully']);
    }


    public function transfer(Request $request, $branchId, $employeeId)
    {
        $employee = Employee::with('user')->where('branch_id', $branchId)->findOrFail($employeeId);
    
        $validated = $request->validate([
            'new_branch_id' => 'required|exists:branches,id',
        ]);
    
        $employee->update(['branch_id' => $validated['new_branch_id']]);
        $employee->user->update(['branch_id' => $validated['new_branch_id']]);
    
        return response()->json(['message' => 'Employee transferred successfully']);
    }
    
    
    /**
     * Remove an employee from storage.
     */
    public function destroy($branchId, $employeeId)
    {
        $branch   = Branch::findOrFail($branchId);
        $employee = $branch->employees()->with('user')->findOrFail($employeeId);

        // Delete photo from storage if exists
        if ($employee->user && $employee->user->photo) {
            Storage::disk('public')->delete($employee->user->photo);
        }

        // Delete the user (which cascades to employee via relationship if you want)
        $employee->user()->delete(); // remove user record
        $employee->delete();         // remove employee record

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    /**
     * Suspend an employee.
     */
    public function suspend($branchId, $employeeId)
    {
        $branch   = Branch::findOrFail($branchId);
        $employee = $branch->employees()->findOrFail($employeeId);

        $employee->update([
            'suspended'      => true,
            'suspensionDate' => now(),
        ]);

        return response()->json(['message' => 'Employee suspended successfully']);
    }

    /**
     * Unsuspend an employee.
     */
    public function unsuspend($branchId, $employeeId)
    {
        $branch   = Branch::findOrFail($branchId);
        $employee = $branch->employees()->findOrFail($employeeId);

        $employee->update([
            'suspended'      => false,
            'suspensionDate' => null,
        ]);

        return response()->json(['message' => 'Employee unsuspended successfully']);
    }
}

