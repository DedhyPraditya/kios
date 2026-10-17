# Kios BERKAH — Mobile Android App (Flutter)

Aplikasi mobile native Android murni (APK) untuk **Kios BERKAH POS & Toko**. Dibangun menggunakan Flutter dengan standar arsitektur bersih (*Clean & Feature-First*), performa tinggi, dan tampilan profesional tanpa kesan *AI slop*.

---

## 📱 Spesifikasi Teknis

- **Target Platform**: Android (APK murni, bukan PWA)
- **Minimum OS**: Android 8.0 Oreo (`minSdk = 26`)
- **Bahasa & Framework**: Flutter 3.44.8 / Dart 3.12.2
- **State Management**: Flutter Riverpod (`flutter_riverpod`)
- **Networking**: `dio` dengan interceptor otomatis Bearer Token (Laravel Sanctum) & dukungan Base URL dinamis (bisa diarahkan ke IP lokal Laragon maupun VPS produksi).
- **Scanner**: Kamera scanner barcode cepat (`mobile_scanner`).
- **Printer Kasir**: Bluetooth thermal printer ESC/POS 58mm / 80mm (`print_bluetooth_thermal` + `esc_pos_utils_plus`).
- **Typography & Desain**: Plus Jakarta Sans (UI) + JetBrains Mono (nominal Rupiah, angka stok, barcode), palet Forest Green `#1E6B4F`, amber `#F4A261`, background netral `#F4F6F5`.

---

## 📂 Struktur Proyek (`mobile/lib`)

```
mobile/lib/
├── core/
│   ├── constants/
│   │   ├── app_colors.dart         # Palet warna resmi Kios BERKAH
│   │   └── api_endpoints.dart      # Rute lengkap endpoint backend Laravel
│   ├── network/
│   │   ├── api_client.dart         # HTTP Client (Dio, Token, Timeout, Error Handler)
│   │   └── api_response.dart       # Pembungkus respon API generik
│   ├── theme/
│   │   └── app_theme.dart          # Tema Material 3, font, tombol, kartu, input
│   ├── utils/
│   │   ├── currency_formatter.dart # Format Rupiah Indonesia (e.g. Rp15.000)
│   │   └── date_formatter.dart     # Format tanggal & jam Indonesia
│   └── widgets/
│       ├── app_button.dart         # Tombol interaktif (Primary, Outline, Danger)
│       ├── app_text_field.dart     # Komponen input seragam
│       ├── stock_badge.dart        # Indikator warna stok (Merah/Amber/Hijau)
│       ├── receipt_tape_decor.dart # Efek visual gerigi kertas struk thermal
│       └── empty_state_view.dart   # Tampilan kosong informatif
│
├── features/
│   ├── auth/                       # Login kasir & sesi pengguna
│   ├── pos/                        # Kasir POS, Scan Barcode, Keranjang, Checkout Tunai/QRIS/Kasbon
│   ├── products/                   # Katalog produk, filter kategori & stok
│   ├── shift/                      # Buka/Tutup kasir, kas masuk/keluar, hitung laci
│   ├── arang/                      # Modul arang kiloan (Beli dari pemasok & Jual ke pelanggan)
│   ├── sales/                      # Riwayat nota transaksi, koreksi, void & retur
│   ├── customers/                  # Data pelanggan, limit kredit, piutang kasbon
│   ├── reports/                    # Rekap omzet harian & produk terlaris
│   ├── settings/                   # Sambungan printer Bluetooth thermal & setelan IP server
│   └── navigation/                 # Bottom Navigation Bar utama
│
├── app.dart                        # Konfigurasi MaterialApp & Riverpod
└── main.dart                       # Entry point aplikasi
```

---

## 🚀 Perintah Dasar

```bash
# Mengunduh dependensi
flutter pub get

# Menjalankan pengujian (Smoke & Unit test)
flutter test

# Menjalankan di perangkat / emulator Android
flutter run

# Membangun file APK rilis
flutter build apk --release
```
