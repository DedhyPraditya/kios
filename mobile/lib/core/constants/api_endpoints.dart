/// Konfigurasi URL dan Endpoint API untuk menghubungkan Flutter ke Laravel backend.
class ApiEndpoints {
  ApiEndpoints._();

  // Default base URL (dapat diubah dinamis lewat Pengaturan di aplikasi Flutter)
  // Untuk emulator Android: 10.0.2.2:8000
  // Untuk device fisik via Wi-Fi: IP LAN server Laragon (contoh: http://192.168.1.10:8000)
  // Untuk domain produksi: https://kios.ndrsolution.my.id/api
  static const String defaultBaseUrl = 'https://kios.ndrsolution.my.id/api';

  // Auth
  static const String login = '/login';
  static const String logout = '/logout';
  static const String user = '/user';
  static const String profile = '/profile';

  // Dashboard & Notifikasi
  static const String dashboard = '/dashboard';
  static const String alertsDismiss = '/alerts/dismiss';

  // POS (Kasir) & Transaksi
  static const String posData = '/pos';
  static const String posStore = '/pos';
  static const String posReceipt = '/pos/struk'; // + /{sale_id}

  // Shift (Tutup Kasir & Laci)
  static const String shiftIndex = '/shift';
  static const String shiftStore = '/shift';
  static const String shiftMovement = '/shift/kas';
  static const String shiftClose = '/shift'; // + /{session_id}/tutup

  // Produk & Kategori
  static const String products = '/products';
  static const String categories = '/categories';
  static const String stockMovements = '/stok';

  // Modul Arang
  static const String arangIndex = '/arang';
  static const String arangRiwayat = '/arang/riwayat';
  static const String arangBeli = '/arang/beli';
  static const String arangJual = '/arang/jual';
  static const String arangJenis = '/arang/jenis';

  // Pelanggan & Piutang (Kasbon)
  static const String customers = '/customers';
  static const String creditPayments = '/credit-payments';
  static const String piutang = '/piutang';

  // Riwayat Penjualan & Koreksi (Batal / Retur)
  static const String sales = '/sales';
  static const String salesVoid = '/sales'; // + /{id}/batal
  static const String salesRefund = '/sales'; // + /{id}/retur

  // Laporan
  static const String reports = '/reports';

  // Pengaturan & Identitas Toko
  static const String settings = '/pengaturan';
}
