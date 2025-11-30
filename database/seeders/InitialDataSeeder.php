<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\DarajaApp;
use App\Models\InventoryItem;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // --- Clear existing data (use delete, child -> parent order) ---
        $driver = DB::getDriverName();

        // Disable FK checks safely across drivers
        if ($driver === 'mysql') {
            Schema::disableForeignKeyConstraints();
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        // Delete children first, then parents (avoid FK violations)
        InventoryItem::query()->delete();
        Employee::query()->delete();
        User::query()->delete();
        Branch::query()->delete();
        DarajaApp::query()->delete();

        // Re-enable FK checks
        if ($driver === 'mysql') {
            Schema::enableForeignKeyConstraints();
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // --- Seed data ---

        // Create DarajaApps for each business type
        $butcheryApp = DarajaApp::create([
            'name' => 'Butchery M-Pesa App',
            'consumer_key' => env('MPESA_CONSUMER_KEY', 'butchery_consumer_key'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET', 'butchery_consumer_secret'),
            'shortcode' => env('MPESA_SHORTCODE', '174379'),  // Butchery till number
            'passkey' => env('MPESA_PASSKEY', 'butchery_passkey'),
            'environment' => env('MPESA_ENV', 'sandbox'),
        ]);

        $gasApp = DarajaApp::create([
            'name' => 'Gas M-Pesa App',
            'consumer_key' => env('MPESA_CONSUMER_KEY', 'gas_consumer_key'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET', 'gas_consumer_secret'),
            'shortcode' => '9549191',  // Gas till number
            'passkey' => env('MPESA_PASSKEY', 'gas_passkey'),
            'environment' => env('MPESA_ENV', 'sandbox'),
        ]);

        $drinksApp = DarajaApp::create([
            'name' => 'Drinks M-Pesa App',
            'consumer_key' => env('MPESA_CONSUMER_KEY', 'drinks_consumer_key'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET', 'drinks_consumer_secret'),
            'shortcode' => '9549192',  // Drinks till number
            'passkey' => env('MPESA_PASSKEY', 'drinks_passkey'),
            'environment' => env('MPESA_ENV', 'sandbox'),
        ]);

        // Create branches and link to DarajaApps
        $branch1 = Branch::create([
            'name' => 'Branch 1 - Butchery Main',
            'tillNumber' => null,  // Not needed when using DarajaApp
            'roleConfig' => 'combined',
            'managerId' => 'OWNER',
            'type' => 'Butchery',
            'daraja_app_id' => $butcheryApp->id,
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch 2 - Gas Downtown',
            'tillNumber' => null,  // Not needed when using DarajaApp
            'roleConfig' => 'distinct',
            'managerId' => 'manager1',
            'type' => 'Gas',
            'daraja_app_id' => $gasApp->id,
        ]);

        $branch3 = Branch::create([
            'name' => 'Branch 3 - Drinks Uptown',
            'tillNumber' => null,  // Not needed when using DarajaApp
            'roleConfig' => 'combined',
            'managerId' => 'OWNER',
            'type' => 'Drinks',
            'daraja_app_id' => $drinksApp->id,
        ]);

        // Users
        $owner = User::create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('owner123'),
            'role' => 'Owner',
            'branch_id' => null,
        ]);

        $manager1 = User::create([
            'username' => 'manager1',
            'email' => 'manager1@example.com',
            'password' => Hash::make('manager123'),
            'role' => 'Manager',
            'branch_id' => $branch2->id,  // Gas branch
        ]);

        $cashier1 = User::create([
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('cashier123'),
            'role' => 'Cashier',
            'branch_id' => $branch2->id,  // Gas branch
        ]);

        $cashier2 = User::create([
            'username' => 'cashier2',
            'email' => 'cashier2@example.com',
            'password' => Hash::make('cashier123'),
            'role' => 'Cashier',
            'branch_id' => $branch1->id,  // Butchery branch
        ]);

        $seller1 = User::create([
            'username' => 'seller1',
            'email' => 'seller1@example.com',
            'password' => Hash::make('seller123'),
            'role' => 'Seller',
            'branch_id' => $branch3->id,  // Drinks branch
        ]);

        // Employees
        Employee::create([
            'branch_id' => $branch2->id,  // Gas branch
            'name' => 'Mary Johnson',
            'idNumber' => 'ID006',
            'position' => 'Manager',
            'experience' => 3,
            'role' => 'Manager',
        ]);

        Employee::create([
            'branch_id' => $branch1->id,  // Butchery branch
            'name' => 'John Smith',
            'idNumber' => 'ID007',
            'position' => 'Cashier',
            'experience' => 1,
            'role' => 'Cashier',
        ]);

        Employee::create([
            'branch_id' => $branch3->id,  // Drinks branch
            'name' => 'Jane Doe',
            'idNumber' => 'ID008',
            'position' => 'Seller',
            'experience' => 2,
            'role' => 'Seller',
        ]);

    }
}
