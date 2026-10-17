<?php

use App\Http\Controllers\Api\PosApiController;
use Illuminate\Support\Facades\Route;

// Autentikasi Kasir
Route::post('/login', [PosApiController::class, 'login']);

// POS (Data Produk, Kategori, Pelanggan, Transaksi)
Route::get('/pos', [PosApiController::class, 'index']);
Route::post('/pos', [PosApiController::class, 'store']);

// Riwayat Penjualan
Route::get('/sales', [PosApiController::class, 'sales']);

// Dashboard Beranda Mobile
Route::get('/dashboard', [PosApiController::class, 'dashboard']);

// QRIS Toko
Route::get('/qris-toko', [\App\Http\Controllers\SettingController::class, 'qris']);
