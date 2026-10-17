class QrisTag {
  final String id;
  final String value;

  const QrisTag(this.id, this.value);
}

/// Helper untuk manipulasi data QRIS standar EMVCo / Bank Indonesia.
/// Mengubah QRIS Statis Toko menjadi QRIS Dinamis ber-nominal otomatis.
class QrisHelper {
  /// Hitung CRC16-CCITT (polynomial 0x1021, init 0xFFFF)
  static String crc16(String text) {
    int crc = 0xFFFF;
    for (int i = 0; i < text.length; i++) {
      crc ^= (text.codeUnitAt(i) << 8) & 0xFFFF;
      for (int b = 0; b < 8; b++) {
        if ((crc & 0x8000) != 0) {
          crc = ((crc << 1) ^ 0x1021) & 0xFFFF;
        } else {
          crc = (crc << 1) & 0xFFFF;
        }
      }
    }
    return crc.toRadixString(16).toUpperCase().padLeft(4, '0');
  }

  /// Parse TLV (Tag-Length-Value)
  static List<QrisTag>? parseTlv(String text) {
    final tags = <QrisTag>[];
    int i = 0;
    while (i < text.length) {
      if (i + 4 > text.length) return null;
      final id = text.substring(i, i + 2);
      final len = int.tryParse(text.substring(i + 2, i + 4));
      if (len == null || i + 4 + len > text.length) return null;
      final value = text.substring(i + 4, i + 4 + len);
      tags.add(QrisTag(id, value));
      i += 4 + len;
    }
    return tags;
  }

  static String _tlv(String id, String value) {
    final lenStr = value.length.toString().padLeft(2, '0');
    return '$id$lenStr$value';
  }

  /// Validasi QRIS format
  static bool isValid(String? payload) {
    if (payload == null || !payload.startsWith('000201') || payload.length < 8) return false;
    final body = payload.substring(0, payload.length - 4);
    if (!body.endsWith('6304')) return false;
    final expectedCrc = payload.substring(payload.length - 4).toUpperCase();
    return crc16(body) == expectedCrc && parseTlv(payload) != null;
  }

  /// Ambil Nama Toko / Merchant dari Tag 59
  static String getMerchantName(String payload) {
    final tags = parseTlv(payload);
    if (tags == null) return '';
    for (final t in tags) {
      if (t.id == '59') return t.value;
    }
    return '';
  }

  /// Ubah QRIS Statis menjadi QRIS Dinamis dengan nominal transaksi otomatis
  static String toDynamic(String payload, int amount) {
    final tags = parseTlv(payload);
    if (tags == null) return payload;

    // Filter tag: 54 (amount), 55-57 (tip), 63 (crc) dibuang lalu disusun ulang
    final rest = <QrisTag>[];
    for (final t in tags) {
      if (['54', '55', '56', '57', '63'].contains(t.id)) continue;
      if (t.id == '01') {
        rest.add(const QrisTag('01', '12')); // 12 = Dinamis
      } else {
        rest.add(t);
      }
    }

    if (amount > 0) {
      rest.add(QrisTag('54', amount.toString()));
    }

    rest.sort((a, b) => (int.tryParse(a.id) ?? 0).compareTo(int.tryParse(b.id) ?? 0));

    final sb = StringBuffer();
    for (final t in rest) {
      sb.write(_tlv(t.id, t.value));
    }
    sb.write('6304');
    final body = sb.toString();
    return body + crc16(body);
  }
}
