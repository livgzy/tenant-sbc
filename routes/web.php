<?php

use App\Http\Controllers\BucketFileController;
use App\Http\Controllers\TenantReportPrintController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::redirect('/', '/dashboard');
Route::middleware(['auth:tenant', 'tenant.access:tenant-dashboard-access'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard.dashboard')->name('home');
    Route::livewire('/dashboard/menu', 'pages::dashboard.dashboard-menu')->name('tenant.menu');
    Route::livewire('/dashboard/menu/add', 'pages::dashboard.dashboard-menu-add')->name('tenant.menu.add');
    Route::livewire('/dashboard/menu/update/{product}', 'pages::dashboard.dashboard-menu-edit')->name('tenant.menu.edit');
    Route::livewire('/dashboard/tenant/profile', 'pages::dashboard.dashboard-tenant-profile')->name('dashboard.tenant.profile');
    Route::livewire('/dashboard/order', 'pages::dashboard.dashboard-order')->name('dashboard.orders');
    Route::livewire('/dashboard/report', 'pages::dashboard.dashboard-report-order')->name('dashboard.reports');
    Route::get('/dashboard/report/print', TenantReportPrintController::class)->name('report.print');
});

Route::middleware(['auth:tenant', 'tenant.access:reservation-access'])->group(function () {
    Route::livewire('/reservasi', 'pages::dashboard.reservasi')->name('tenant.reservation');
});

Route::middleware(['auth:tenant', 'tenant.access:tenant-payout-access'])->group(function () {
    Route::livewire('/payout', 'pages::dashboard.dashboard-payout')->name('tenant.payout');
    Route::livewire('/payout/payment', 'pages::dashboard.dashboard-tenant-payment')->name('payout.payment');
    Route::livewire('/payout/order', 'pages::dashboard.dashboard-payout-order')->name('payout.order');
    Route::livewire('/payout/report', 'pages::dashboard.dashboard-payout-report')->name('payout.report');
});

Route::middleware('guest:tenant')->group(function () {
    Route::livewire('/login', 'pages::dashboard.dashboard-login')->name('login');
    Route::livewire('/register', 'pages::dashboard.dashboard-register')->name('register');
});


Route::middleware('auth:tenant')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
});

Route::get('/files/{path}', [BucketFileController::class, 'show'])
    ->where('path', '.*')
    ->name('bucket.file');
