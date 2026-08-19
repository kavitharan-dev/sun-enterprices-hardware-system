<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SmsLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Cashier\DailyAccountController;
use App\Http\Controllers\Construction\DailyProgressController;
use App\Http\Controllers\Construction\MaterialRequestController;
use App\Http\Controllers\Construction\ProjectController;
use App\Http\Controllers\Construction\ProjectExpenseController;
use App\Http\Controllers\Construction\ProjectOwnerPaymentController;
use App\Http\Controllers\Construction\WorkerController;
use App\Http\Controllers\Construction\WorkerPayrollController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Store\BrandController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\CustomerController;
use App\Http\Controllers\Store\InventoryController;
use App\Http\Controllers\Store\MaterialIssueController;
use App\Http\Controllers\Store\MaterialRequestReviewController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\PurchaseController;
use App\Http\Controllers\Store\SaleController;
use App\Http\Controllers\Store\SupplierController;
use App\Http\Controllers\Store\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['auth', 'role:admin|store_manager|cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('daily-accounts', [DailyAccountController::class, 'index'])->name('daily-accounts.index');
    Route::post('daily-accounts', [DailyAccountController::class, 'store'])->name('daily-accounts.store');
    Route::put('daily-accounts/opening', [DailyAccountController::class, 'updateOpening'])->name('daily-accounts.opening');
    Route::delete('daily-accounts/{entry}', [DailyAccountController::class, 'destroy'])->name('daily-accounts.destroy');
});

Route::middleware(['auth', 'role:admin|store_manager|site_manager'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('sales', [ReportController::class, 'sales'])->name('sales');
    Route::get('purchases', [ReportController::class, 'purchases'])->name('purchases');
    Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
    Route::get('movements', [ReportController::class, 'movements'])->name('movements');
    Route::get('outstanding', [ReportController::class, 'outstanding'])->name('outstanding');
    Route::get('projects', [ReportController::class, 'projects'])->name('projects');
    Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
    Route::get('issues', [ReportController::class, 'issues'])->name('issues');
});

Route::middleware(['auth', 'role:admin|store_manager'])->prefix('store')->name('store.')->group(function () {
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('brands', BrandController::class)->except('show');
    Route::resource('units', UnitController::class)->except('show');
    Route::resource('products', ProductController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('purchases', PurchaseController::class);
    Route::post('purchases/{purchase}/complete', [PurchaseController::class, 'complete'])->name('purchases.complete');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

    Route::get('material-requests', [MaterialRequestReviewController::class, 'index'])->name('material-requests.index');
    Route::get('material-requests/{materialRequest}', [MaterialRequestReviewController::class, 'show'])->name('material-requests.show');
    Route::post('material-requests/{materialRequest}/approve', [MaterialRequestReviewController::class, 'approve'])->name('material-requests.approve');
    Route::post('material-requests/{materialRequest}/reject', [MaterialRequestReviewController::class, 'reject'])->name('material-requests.reject');
    Route::post('material-requests/{materialRequest}/issue', [MaterialIssueController::class, 'store'])->name('material-requests.issue');
    Route::get('material-issues', [MaterialIssueController::class, 'index'])->name('material-issues.index');
    Route::get('material-issues/{materialIssue}', [MaterialIssueController::class, 'show'])->name('material-issues.show');
});

Route::middleware(['auth', 'role:admin|store_manager|cashier'])->prefix('store')->name('store.')->group(function () {
    Route::resource('customers', CustomerController::class);

    Route::get('sales/{sale}/bill', [SaleController::class, 'bill'])->name('sales.bill');
    Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
    Route::get('sales/{sale}/invoice/download', [SaleController::class, 'invoiceDownload'])->name('sales.invoice.download');
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    Route::post('sales/{sale}/complete', [SaleController::class, 'complete'])->name('sales.complete');
    Route::post('sales/{sale}/pay', [SaleController::class, 'pay'])->name('sales.pay');
    Route::resource('sales', SaleController::class);
});

Route::middleware(['auth', 'role:admin|site_manager'])->prefix('construction')->name('construction.')->group(function () {
    Route::post('projects/{project}/workers', [ProjectController::class, 'assignWorker'])->name('projects.workers.store');
    Route::delete('projects/{project}/workers/{pivot}', [ProjectController::class, 'unassignWorker'])->name('projects.workers.destroy');
    Route::get('projects/{project}/dashboard', [ProjectController::class, 'dashboard'])->name('projects.dashboard');
    Route::post('projects/{project}/progress', [DailyProgressController::class, 'store'])->name('projects.progress.store');
    Route::put('projects/{project}/progress/{dailyProgress}', [DailyProgressController::class, 'update'])->name('projects.progress.update');
    Route::delete('projects/{project}/progress/{dailyProgress}', [DailyProgressController::class, 'destroy'])->name('projects.progress.destroy');
    Route::post('projects/{project}/expenses', [ProjectExpenseController::class, 'store'])->name('projects.expenses.store');
    Route::delete('projects/{project}/expenses/{projectExpense}', [ProjectExpenseController::class, 'destroy'])->name('projects.expenses.destroy');
    Route::post('projects/{project}/owner-payments', [ProjectOwnerPaymentController::class, 'store'])->name('projects.owner-payments.store');
    Route::delete('projects/{project}/owner-payments/{ownerPayment}', [ProjectOwnerPaymentController::class, 'destroy'])->name('projects.owner-payments.destroy');
    Route::resource('projects', ProjectController::class);

    Route::get('payroll', [WorkerPayrollController::class, 'index'])->name('payroll.index');
    Route::get('workers/{worker}/payroll', [WorkerPayrollController::class, 'show'])->name('workers.payroll');
    Route::post('workers/{worker}/payroll/advances', [WorkerPayrollController::class, 'storeAdvance'])->name('workers.payroll.advances.store');
    Route::post('workers/{worker}/payroll/weeks/{week}/settle', [WorkerPayrollController::class, 'settle'])->name('workers.payroll.settle');
    Route::post('workers/{worker}/payroll/weeks/{week}/reopen', [WorkerPayrollController::class, 'reopen'])->name('workers.payroll.reopen');
    Route::post('workers/{worker}/payroll/work-days', [WorkerPayrollController::class, 'storeWorkDay'])->name('workers.payroll.work-days.store');
    Route::put('workers/{worker}/payroll/work-days/{workDay}', [WorkerPayrollController::class, 'updateWorkDay'])->name('workers.payroll.work-days.update');
    Route::delete('workers/{worker}/payroll/work-days/{workDay}', [WorkerPayrollController::class, 'destroyWorkDay'])->name('workers.payroll.work-days.destroy');
    Route::resource('workers', WorkerController::class);

    Route::post('material-requests/{materialRequest}/submit', [MaterialRequestController::class, 'submit'])->name('material-requests.submit');
    Route::resource('material-requests', MaterialRequestController::class);
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('sms-logs', [SmsLogController::class, 'index'])->name('sms-logs.index');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});

require __DIR__.'/auth.php';
