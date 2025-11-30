<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Employee;

class EmployeePolicy
{
    public function manage(User $user){ return $user->role === 'Owner' || $user->role === 'Manager'; }
}
