<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ButcheryInventoryItemController;
use App\Http\Controllers\GasInventoryItemController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseItemController;
use App\Http\Controllers\WarehouseDispatchController;
use App\Http\Controllers\CreditSaleController;
use App\Http\Controllers\CreditRepaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BillController;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('/auth/token', [AuthController::class, 'token']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
Route::get('/health/{system}', [HealthController::class, 'check']);
Route::get('/health/{system}', [HealthController::class, 'check']);
Route::post('/mpesa/c2b/confirmation', [MpesaController::class, 'c2bConfirmation'])->name('mpesa.c2b.callback');
Route::post('/mpesa/c2b/validation', [MpesaController::class, 'c2bValidation'])->name('mpesa.c2b.validation');
Route::post('/mpesa/register-c2b', [MpesaController::class, 'registerC2BUrls']);
Route::post('/mpesa/stkpush/callback', [MpesaController::class, 'stkCallback'])->name('mpesa.stk.callback');
Route::post('/mpesa/callback/{appId}', [MpesaController::class, 'stkCallback'])->name('mpesa.callback');

// 🔊 Broadcasting authentication
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// 🌐 News proxy endpoint
Route::get('/news', function (Request $request) {
    $q = $request->query('q', 'meat OR butchery OR fuel OR gas');
    $page = $request->query('page', 1);
    $response = Http::get('https://newsapi.org/v2/everything', [
        'q' => $q,
        'language' => 'en',
        'pageSize' => 20,
        'page' => $page,
        'apiKey' => env('NEWSAPI_KEY'),
    ]);
    return response()->json($response->json());
});

// 🔐 Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('/user', [UserController::class, 'getProfile']);
    Route::put('/user', [UserController::class, 'updateProfile']);
    Route::post('/user/update-photo', [UserController::class, 'updatePhoto']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    Route::get('/search', [SearchController::class, 'search']);

    // 🌿 Branches
    Route::apiResource('branches', BranchController::class);
    Route::get('/inventory/performance/{branchId}', [InventoryItemController::class, 'performance']);


    // 🧭 Nested branch-specific resources
    Route::prefix('branches/{branch}')->group(function () {
        // 👷 Employees
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::patch('employees/{employeeId}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employeeId}', [EmployeeController::class, 'destroy']);
        Route::post('employees/{employeeId}/transfer', [EmployeeController::class, 'transfer']);
        Route::post('employees/{employeeId}/suspend', [EmployeeController::class, 'suspend']);
        Route::post('employees/{employeeId}/unsuspend', [EmployeeController::class, 'unsuspend']);

        // 📦 Inventory
        Route::get('/inventory', [InventoryItemController::class, 'index']);
        Route::post('/inventory', [InventoryItemController::class, 'store']);
        Route::put('/inventory/{item}', [InventoryItemController::class, 'update']);
        Route::delete('/inventory/{item}', [InventoryItemController::class, 'destroy']);

        // 💸 Sales & Payments
        Route::get('/sales', [SaleController::class, 'branchSales']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/grouped', [PaymentController::class, 'grouped']);
        Route::get('/payments/{sale}', [PaymentController::class, 'show']);


        // 📊 Branch stats
        Route::get('/statistics', [BranchController::class, 'statistics']);
        Route::get('/sales/statistics', [SaleController::class, 'branchStatistics']);
        Route::get('/expenses/grouped', [ExpenseController::class, 'grouped']);
    });

    // 💰 Expenses
    Route::prefix('branches/{branch}/expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
    });

    // 🧑‍💼 Employees (flat)
    Route::apiResource('employees', EmployeeController::class)->only(['index', 'show', 'destroy']);
    Route::patch('employees/{employee}/suspend', [EmployeeController::class, 'suspend']);
    Route::patch('employees/{employee}/unsuspend', [EmployeeController::class, 'unsuspend']);
    Route::get('/employees/productivity', [EmployeeController::class, 'productivity']); // new metric

    // 🧾 Inventory types
    Route::get('/butchery-inventory-items', [ButcheryInventoryItemController::class, 'index']);
    Route::get('/gas-inventory-items', [GasInventoryItemController::class, 'index']);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('warehouse-items', WarehouseItemController::class);
    Route::apiResource('warehouse-dispatches', WarehouseDispatchController::class)->only(['index', 'show']);
    Route::apiResource('credit-sales', CreditSaleController::class)->only(['index', 'show']);
    Route::post('credit-sales/{credit_sale}/pay', [CreditSaleController::class, 'pay']);
    Route::apiResource('credit-repayments', CreditRepaymentController::class)->only(['index', 'show', 'update']);

    Route::prefix('analytics')->group(function () {
        Route::get('/sales/grouped', [SalesReportController::class, 'grouped']);
        Route::get('/products/distribution', [SalesReportController::class, 'productsDistribution']);
        Route::get('/payments/grouped', [SalesReportController::class, 'paymentsGrouped']);
        Route::get('/payments/grouped/all', [SalesReportController::class, 'paymentsGroupedAll']);
        Route::get('/financials/revenue-expense-profit', [SalesReportController::class, 'revenueExpenseProfit']);
        Route::get('/stock/turnover', [SalesReportController::class, 'stockTurnover']);
        Route::get('/customers/new-returning', [SalesReportController::class, 'newReturningCustomers']);

        // 🧠 New endpoints
        Route::get('/inventory/intelligence', [SalesReportController::class, 'inventoryIntelligence']);
        Route::get('/customers/behavior', [SalesReportController::class, 'customerBehaviorInsights']);
    });


    // 💳 Card system
    Route::post('/cards/register', [CardController::class, 'register']);
    Route::post('/cards/redeem', [CardController::class, 'redeem']);
    Route::post('/cards/topup', [CardController::class, 'topup']);

    // 💵 M-Pesa integrations
    Route::post('sales/mpesa/start', [SaleController::class, 'startMpesaPayment']);
    Route::post('/mpesa/verify', [MpesaController::class, 'verifyTransaction']);
    Route::post('/mpesa/c2b/initiate', [MpesaController::class, 'initiateC2BPayment']);
    Route::get('sales/mpesa-status/{reference}', [SaleController::class, 'mpesaStatus']);

    // 🧾 Sales endpoints
    Route::apiResource('sales', SaleController::class);
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/pay', [SaleController::class, 'pay']);

    Route::apiResource('branches.bills', BillController::class);
    Route::post('branches/{branch}/bills/{bill}/pay', [BillPaymentController::class, 'store']);
    Route::get('branches/{branch}/bills/{bill}/payments', [BillPaymentController::class, 'index']);

    Route::get('branches/{branch}/bill-payments', [PaymentController::class, 'billPayments']);
    Route::post('branches/{branch}/pay-bill', [PaymentController::class, 'payBill']);

    Route::get('/search/suggestions', [PaymentController::class, 'suggestions']);
    Route::get('/payments/search/{query}', [PaymentController::class, 'search']);

});


