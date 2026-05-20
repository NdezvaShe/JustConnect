<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

/* ── Public ─────────────────────────────────────── */
Route::get('/',        [AuthController::class, 'landing'])->name('landing');
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login']);
Route::get('/login/mfa', [AuthController::class, 'showMfaChallenge'])->name('mfa.challenge');
Route::post('/login/mfa', [AuthController::class, 'verifyMfa'])->name('mfa.verify');
Route::post('/login/mfa/resend', [AuthController::class, 'resendMfa'])->name('mfa.resend');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetOtp'])->name('password.otp.send');
Route::post('/forgot-password/resend', [AuthController::class, 'resendPasswordResetOtp'])->name('password.otp.resend');
Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
Route::get('/register',        [AuthController::class, 'showRegister'])->name('register');
Route::post('/register',       [AuthController::class, 'register']);
Route::post('/register/verify',[AuthController::class, 'verifyOtp'])->name('otp.verify');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/* ── Authenticated ──────────────────────────────── */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DocumentController::class, 'dashboard'])->name('dashboard');

    // Document management
    Route::post('/documents/upload',    [DocumentController::class, 'upload'])->name('documents.upload');
    Route::post('/documents/extract',   [DocumentController::class, 'extract'])->name('documents.extract');
    Route::post('/documents/summarise', [DocumentController::class, 'summarise'])->name('documents.summarise');
    Route::get('/documents/{id}/status', [DocumentController::class, 'analysisStatus'])->name('documents.status');
    Route::get('/documents/{id}/export/{format}/file', [DocumentController::class, 'downloadExport'])->name('documents.export.file');
    Route::get('/documents/{id}/export/{format}', [DocumentController::class, 'export'])->name('documents.export');
    Route::post('/documents/{id}/email', [DocumentController::class, 'emailSummary'])->name('documents.email');
    Route::get('/documents/{id}',       [DocumentController::class, 'show'])->name('documents.show');
    Route::delete('/documents/{id}',    [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Records & downloads
    Route::get('/records',   [DocumentController::class, 'records'])->name('records');
    Route::get('/downloads', [DocumentController::class, 'downloads'])->name('downloads');
    Route::post('/downloads/log', [DocumentController::class, 'logDownload'])->name('downloads.log');
    Route::get('/admin/dataset', [DocumentController::class, 'adminDataset'])->name('admin.dataset');
    Route::get('/admin/dataset/export', [DocumentController::class, 'exportAdminDataset'])->name('admin.dataset.export');

    // Profile
    Route::post('/profile',          [DocumentController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password', [DocumentController::class, 'updatePassword'])->name('profile.password');
    Route::post('/settings/mfa',     [DocumentController::class, 'updateMfa'])->name('settings.mfa');
});
