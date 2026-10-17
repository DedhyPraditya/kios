import 'package:intl/intl.dart';

/// Formatter tanggal dan waktu standar Indonesia dengan penanganan fallback aman.
class DateFormatter {
  DateFormatter._();

  static String format(DateTime? dateTime) {
    if (dateTime == null) return '-';
    try {
      return DateFormat('dd MMM yyyy', 'id_ID').format(dateTime);
    } catch (_) {
      return DateFormat('dd-MM-yyyy').format(dateTime);
    }
  }

  static String formatWithTime(DateTime? dateTime) {
    if (dateTime == null) return '-';
    try {
      return DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(dateTime);
    } catch (_) {
      return DateFormat('dd-MM-yyyy HH:mm').format(dateTime);
    }
  }

  static String formatTime(DateTime? dateTime) {
    if (dateTime == null) return '-';
    try {
      return DateFormat('HH:mm', 'id_ID').format(dateTime);
    } catch (_) {
      return DateFormat('HH:mm').format(dateTime);
    }
  }
}
