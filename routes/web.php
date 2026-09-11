<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SecureFileController;
use App\Livewire\AttendancePage;
use App\Livewire\Dashboard;
use App\Livewire\ExpensesPage;
use App\Livewire\ManagementPage;
use App\Livewire\PosPage;
use App\Livewire\ProductsPage;
use App\Livewire\ReportsPage;
use App\Livewire\TransactionsPage;
use App\Livewire\UserAccountsPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/pos', PosPage::class)->name('pos');
    Route::get('/transactions', TransactionsPage::class)->name('transactions');
    Route::get('/transactions/{order}/receipt', ReceiptController::class)->name('transactions.receipt');
    Route::get('/attendance', AttendancePage::class)->name('attendance');
    Route::get('/attendance/{attendance}/{type}/photo', [SecureFileController::class, 'attendance'])->name('attendance.photo');

    Route::middleware('owner')->group(function () {
        Route::get('/products', ProductsPage::class)->name('products');
        Route::get('/expenses', ExpensesPage::class)->name('expenses');
        Route::get('/expenses/attachments/{attachment}', [SecureFileController::class, 'expense'])->name('expenses.attachment');
        Route::get('/reports', ReportsPage::class)->name('reports');
        Route::get('/reports/export', ReportExportController::class)->name('reports.export');
        Route::get('/management', ManagementPage::class)->name('management');
        Route::get('/user-login', UserAccountsPage::class)->name('user-accounts');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
