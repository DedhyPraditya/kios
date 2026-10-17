import 'package:intl/intl.dart';

/// Formatter tanggal dan waktu standar Indonesia.
class DateFormatter {
  DateFormatter._();

  static final DateFormat _dateOnly = DateFormat('dd MMM yyyy', 'id_ID');
  static final DateFormat _dateTime = DateFormat('dd MMM yyyy, HH:mm', 'id_ID');
  static final DateFormat _timeOnly = DateFormat('HH:mm', 'id_ID');

  static String format(DateTime? dateTime) {
    if (dateTime == null) return '-';
    return _dateOnly.format(dateTime);
  }

  static String formatWithTime(DateTime? dateTime) {
    if (dateTime == null) return '-';
    return _dateTime.format(dateTime);
  }

  static String formatTime(DateTime? dateTime) {
    if (dateTime == null) return '-';
    return _timeOnly.format(dateTime);
  }
}
