import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'app.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Inisialisasi locale data bahasa Indonesia untuk format tanggal & mata uang
  try {
    await initializeDateFormatting('id_ID', null);
  } catch (_) {
    // Fallback jika asset locale gagal dimuat
  }

  // Set status bar bersih transparan dengan ikon gelap untuk tema putih
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
      statusBarBrightness: Brightness.light,
      systemNavigationBarColor: Colors.white,
      systemNavigationBarIconBrightness: Brightness.dark,
    ),
  );

  runApp(
    const ProviderScope(
      child: KiosBerkahApp(),
    ),
  );
}
