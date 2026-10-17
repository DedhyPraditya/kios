import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';

class BluetoothPrinterService {
  static const String _prefMacKey = 'printer_mac_address';
  static const String _prefNameKey = 'printer_device_name';

  static final BluetoothPrinterService instance = BluetoothPrinterService._();
  BluetoothPrinterService._();

  String? connectedMac;
  String? connectedName;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    connectedMac = prefs.getString(_prefMacKey);
    connectedName = prefs.getString(_prefNameKey);

    if (connectedMac != null && connectedMac!.isNotEmpty) {
      final isConnected = await PrintBluetoothThermal.connectionStatus;
      if (!isConnected) {
        // Coba hubungkan kembali ke printer terakhir yang tersimpan
        try {
          await PrintBluetoothThermal.connect(macPrinterAddress: connectedMac!);
        } catch (e) {
          debugPrint('Gagal auto-connect printer: $e');
        }
      }
    }
  }

  Future<bool> isBluetoothEnabled() async {
    return await PrintBluetoothThermal.bluetoothEnabled;
  }

  Future<bool> isConnected() async {
    return await PrintBluetoothThermal.connectionStatus;
  }

  Future<List<BluetoothInfo>> getPairedPrinters() async {
    return await PrintBluetoothThermal.pairedBluetooths;
  }

  Future<bool> connect(String mac, String name) async {
    final result = await PrintBluetoothThermal.connect(macPrinterAddress: mac);
    if (result) {
      connectedMac = mac;
      connectedName = name;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefMacKey, mac);
      await prefs.setString(_prefNameKey, name);
    }
    return result;
  }

  Future<bool> disconnect() async {
    final result = await PrintBluetoothThermal.disconnect;
    connectedMac = null;
    connectedName = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefMacKey);
    await prefs.remove(_prefNameKey);
    return result;
  }

  Future<bool> printTestReceipt() async {
    final isConn = await PrintBluetoothThermal.connectionStatus;
    if (!isConn) {
      if (connectedMac != null) {
        final reconnected = await PrintBluetoothThermal.connect(macPrinterAddress: connectedMac!);
        if (!reconnected) return false;
      } else {
        return false;
      }
    }

    final profile = await CapabilityProfile.load();
    final generator = Generator(PaperSize.mm58, profile);
    List<int> bytes = [];

    bytes += generator.reset();
    bytes += generator.text(
      'KIOS BERKAH',
      styles: const PosStyles(
        align: PosAlign.center,
        height: PosTextSize.size2,
        width: PosTextSize.size2,
        bold: true,
      ),
    );
    bytes += generator.text(
      'Sistem Kasir Pintar & Modern',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += generator.hr();
    bytes += generator.text(
      'TES KONEKSI PRINTER',
      styles: const PosStyles(align: PosAlign.center, bold: true),
    );
    bytes += generator.text(
      'Status: BERHASIL TERSAMBUNG',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += generator.text(
      'Printer: ${connectedName ?? "RPP02N / 58mm"}',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += generator.hr();
    bytes += generator.text(
      'Alhamdulillah',
      styles: const PosStyles(align: PosAlign.center, bold: true),
    );
    bytes += generator.feed(2);
    bytes += generator.cut();

    return await PrintBluetoothThermal.writeBytes(bytes);
  }
}
