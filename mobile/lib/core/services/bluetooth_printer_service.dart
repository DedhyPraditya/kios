import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/features/sales/data/models/sale_model.dart';

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

  Future<bool> printSaleReceipt(SaleModel sale, {String storeName = 'KIOS BERKAH'}) async {
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
      storeName,
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

    bytes += generator.row([
      PosColumn(text: 'No Nota:', width: 4),
      PosColumn(text: sale.invoiceNumber, width: 8, styles: const PosStyles(align: PosAlign.right, bold: true)),
    ]);
    bytes += generator.row([
      PosColumn(text: 'Waktu:', width: 4),
      PosColumn(
        text: '${sale.createdAt.day.toString().padLeft(2, '0')}/${sale.createdAt.month.toString().padLeft(2, '0')} ${sale.createdAt.hour.toString().padLeft(2, '0')}:${sale.createdAt.minute.toString().padLeft(2, '0')}',
        width: 8,
        styles: const PosStyles(align: PosAlign.right),
      ),
    ]);
    if (sale.customerName != null) {
      bytes += generator.row([
        PosColumn(text: 'Pelanggan:', width: 4),
        PosColumn(text: sale.customerName!, width: 8, styles: const PosStyles(align: PosAlign.right)),
      ]);
    }
    bytes += generator.hr();

    for (final item in sale.items) {
      bytes += generator.text(item.productName, styles: const PosStyles(bold: true));
      bytes += generator.row([
        PosColumn(text: '${item.qty} x ${item.price}', width: 6),
        PosColumn(text: '${item.subtotal}', width: 6, styles: const PosStyles(align: PosAlign.right)),
      ]);
    }

    bytes += generator.hr();
    bytes += generator.row([
      PosColumn(text: 'Total:', width: 6, styles: const PosStyles(bold: true)),
      PosColumn(text: 'Rp ${sale.total}', width: 6, styles: const PosStyles(align: PosAlign.right, bold: true)),
    ]);
    bytes += generator.row([
      PosColumn(text: 'Bayar (${sale.paymentMethod.toUpperCase()}):', width: 7),
      PosColumn(text: 'Rp ${sale.paid}', width: 5, styles: const PosStyles(align: PosAlign.right)),
    ]);
    if (sale.change > 0) {
      bytes += generator.row([
        PosColumn(text: 'Kembali:', width: 6),
        PosColumn(text: 'Rp ${sale.change}', width: 6, styles: const PosStyles(align: PosAlign.right)),
      ]);
    }

    bytes += generator.hr();
    bytes += generator.text(
      'Terima Kasih atas Kunjungan Anda!',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += generator.feed(2);
    bytes += generator.cut();

    return await PrintBluetoothThermal.writeBytes(bytes);
  }
}

