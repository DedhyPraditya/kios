import 'package:intl/intl.dart';

/// Formatter mata uang standar Rupiah sesuai kaidah bisnis Kios BERKAH.
/// Tanpa desimal koma (karena rupiah selalu bulat).
class CurrencyFormatter {
  CurrencyFormatter._();

  static final NumberFormat _formatter = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  );

  static final NumberFormat _compactFormatter = NumberFormat.compactCurrency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 1,
  );

  /// Format lengkap: e.g. Rp15.000
  static String format(num? amount) {
    if (amount == null) return 'Rp0';
    return _formatter.format(amount).replaceAll('Rp ', 'Rp');
  }

  /// Format ringkas untuk kartu dasbor / metrik: e.g. Rp1,5 jt
  static String formatCompact(num? amount) {
    if (amount == null) return 'Rp0';
    return _compactFormatter.format(amount);
  }

  /// Membersihkan input teks menjadi angka integer
  static int parseClean(String input) {
    final cleaned = input.replaceAll(RegExp(r'[^0-9]'), '');
    return int.tryParse(cleaned) ?? 0;
  }
}
