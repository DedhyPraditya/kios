<?php

use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos.index'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Kasir + admin
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/struk/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');

    // Tutup kasir: kasir mengelola shift-nya sendiri, admin bisa semua.
    Route::get('/shift', [CashSessionController::class, 'index'])->name('shift.index');
    Route::post('/shift', [CashSessionController::class, 'store'])->name('shift.store');
    Route::post('/shift/kas', [CashSessionController::class, 'movement'])->name('shift.movement');
    Route::post('/shift/{session}/tutup', [CashSessionController::class, 'close'])->name('shift.close');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin only
    Route::middleware('admin')->group(function () {
        Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('customers', CustomerController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('/credit-payments', [CreditPaymentController::class, 'store'])->name('credit-payments.store');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // Riwayat transaksi + koreksi nota (ubah keterangan, batal, retur).
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::patch('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::post('/sales/{sale}/batal', [SaleController::class, 'void'])->name('sales.void');
        Route::post('/sales/{sale}/retur', [SaleController::class, 'refund'])->name('sales.refund');

        // Barang masuk & buku besar stok.
        Route::get('/stok', [StockMovementController::class, 'index'])->name('stock.index');
        Route::post('/stok', [StockMovementController::class, 'store'])->name('stock.store');

        // Identitas toko untuk struk & tampilan.
        Route::get('/pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/piutang', [ReceivableController::class, 'index'])->name('piutang.index');
    });
});

require __DIR__.'/auth.php';
