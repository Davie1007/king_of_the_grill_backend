<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Branch;

class BranchPolicy
{
    public function view(User $user, Branch $branch)
    {
        return $user->role === 'Owner' || $user->branch_id === $branch->id;
    }

    public function update(User $user, Branch $branch)
    {
        return $user->role === 'Owner' ||
            ($user->role === 'Manager' && $user->branch_id === $branch->id);
    }

    public function viewInventory(User $user, Branch $branch)
    {
        // ✅ Allow "Owner" or admin to view inventory for any branch
        if (($user->is_admin ?? false) || $user->role === 'Owner') {
            return true;
        }

        // ✅ Other users can only view their own branch’s inventory
        return isset($user->branch_id) && $user->branch_id == $branch->id;
    }
}
