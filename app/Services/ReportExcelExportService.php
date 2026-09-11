<?php

namespace App\Services;

use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExcelExportService
{
    private const THEME_DARK = '0F766E'; // Deep Emerald / Teal
    private const THEME_SLATE = '1E293B'; // Deep Navy Slate
    private const BG_ZEBRA = 'F8FAFC'; // Soft Slate
    private const BG_TOTAL = 'F1F5F9'; // Accounting Total row
    private const BORDER_COLOR = 'CBD5E1'; // Soft gray border

    public function generate(
        string $storeName,
        CarbonInterface $from,
        CarbonInterface $to,
        array $summary,
        array $breakdown,
        $daily,
        array $topProducts,
        $sales,
        $arangJuals,
        $arangBelis,
        array $piutang
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($storeName)
            ->setLastModifiedBy($storeName)
            ->setTitle("Laporan Keuangan Terpadu - {$storeName}")
            ->setSubject('Laporan Keuangan dan Penjualan')
            ->setDescription("Laporan Keuangan Terpadu Toko & Modul Arang periode {$from->format('d/m/Y')} s/d {$to->format('d/m/Y')}");

        // Sheet 1: Ringkasan Eksekutif
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Ringkasan Eksekutif');
        $this->buildSheetRingkasan($sheet1, $storeName, $from, $to, $summary, $breakdown, $daily, $topProducts, $piutang);

        // Sheet 2: Penjualan Toko Eceran
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Penjualan Toko Eceran');
        $this->buildSheetToko($sheet2, $storeName, $from, $to, $sales);

        // Sheet 3: Penjualan Arang Kiloan
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Penjualan Arang Kiloan');
        $this->buildSheetArangJual($sheet3, $storeName, $from, $to, $arangJuals);

        // Sheet 4: Pembelian Stok Arang
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Pembelian Stok Arang');
        $this->buildSheetArangBeli($sheet4, $storeName, $from, $to, $arangBelis);

        // Set default active sheet to Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function applyHeaderTitle(Worksheet $sheet, string $storeName, string $title, CarbonInterface $from, CarbonInterface $to, string $lastCol): void
    {
        $sheet->setShowGridLines(true);

        // Store Name
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($storeName));
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB(self::THEME_DARK);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Subtitle
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $title);
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true)->getColor()->setRGB(self::THEME_SLATE);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Meta info
        $sheet->mergeCells("A3:{$lastCol}3");
        $meta = 'Periode: ' . $from->format('d/m/Y') . ' s/d ' . $to->format('d/m/Y') . '   |   Waktu Unduh: ' . now()->format('d/m/Y H:i:s') . ' WIB';
        $sheet->setCellValue('A3', $meta);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');
        $sheet->getRowDimension(3)->setRowHeight(18);
    }

    private function buildSheetRingkasan(
        Worksheet $sheet,
        string $storeName,
        CarbonInterface $from,
        CarbonInterface $to,
        array $summary,
        array $breakdown,
        $daily,
        array $topProducts,
        array $piutang
    ): void {
        $this->applyHeaderTitle($sheet, $storeName, 'LAPORAN RINGKASAN EKSEKUTIF KEUANGAN TERPADU', $from, $to, 'E');

        // SECTION 1: RINGKASAN INDIKATOR UTAMA
        $row = 5;
        $sheet->setCellValue("A{$row}", 'I. RINGKASAN INDIKATOR UTAMA');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::THEME_SLATE);

        $row++;
        $tableHeaderRow = $row;
        $sheet->setCellValue("A{$row}", 'Indikator Keuangan');
        $sheet->setCellValue("B{$row}", 'Nilai Gabungan (Total)');
        $sheet->setCellValue("C{$row}", 'Toko Eceran');
        $sheet->setCellValue("D{$row}", 'Modul Arang Kiloan');
        $sheet->setCellValue("E{$row}", 'Keterangan');

        $this->styleTableHeader($sheet, "A{$row}:E{$row}", self::THEME_DARK);

        $kpis = [
            [
                'name' => 'Total Omzet Penjualan',
                'total' => $summary['omzet'] ?? 0,
                'toko' => $breakdown['toko']['omzet'] ?? 0,
                'arang' => $breakdown['arang']['omzet'] ?? 0,
                'format' => 'currency',
                'note' => 'Pendapatan kotor dari transaksi lunas & piutang',
            ],
            [
                'name' => 'Estimasi Laba Kotor',
                'total' => $summary['profit'] ?? 0,
                'toko' => $breakdown['toko']['profit'] ?? 0,
                'arang' => $breakdown['arang']['profit'] ?? 0,
                'format' => 'currency',
                'note' => 'Omzet dikurangi HPP barang & harga beli default arang',
            ],
            [
                'name' => 'Jumlah Transaksi',
                'total' => $summary['count'] ?? 0,
                'toko' => $breakdown['toko']['count'] ?? 0,
                'arang' => $breakdown['arang']['count'] ?? 0,
                'format' => 'number',
                'note' => 'Total nota transaksi penjualan tercatat',
            ],
            [
                'name' => 'Total Diskon Diberikan',
                'total' => $summary['discount'] ?? 0,
                'toko' => $breakdown['toko']['discount'] ?? 0,
                'arang' => $breakdown['arang']['discount'] ?? 0,
                'format' => 'currency',
                'note' => 'Potongan harga promosi / diskon langsung',
            ],
            [
                'name' => 'Volume Arang Terjual',
                'total' => $breakdown['arang']['berat_kg'] ?? 0,
                'toko' => '-',
                'arang' => $breakdown['arang']['berat_kg'] ?? 0,
                'format' => 'kg',
                'note' => 'Total bobot timbangan arang terjual',
            ],
            [
                'name' => 'Pembelian Stok Arang dari Pembuat',
                'total' => $breakdown['arang']['beli_stok'] ?? 0,
                'toko' => '-',
                'arang' => $breakdown['arang']['beli_stok'] ?? 0,
                'format' => 'currency',
                'note' => 'Total pengeluaran kulakan arang dari pengrajin',
            ],
            [
                'name' => 'Piutang Berjalan (Belum Lunas)',
                'total' => $piutang['total'] ?? 0,
                'toko' => $piutang['toko'] ?? 0,
                'arang' => $piutang['arang'] ?? 0,
                'format' => 'currency',
                'note' => 'Sisa tagihan pelanggan yang belum dibayar',
            ],
        ];

        foreach ($kpis as $kpi) {
            $row++;
            $sheet->setCellValue("A{$row}", $kpi['name']);
            $this->setFormattedValue($sheet, "B{$row}", $kpi['total'], $kpi['format'], true);
            $this->setFormattedValue($sheet, "C{$row}", $kpi['toko'], $kpi['format']);
            $this->setFormattedValue($sheet, "D{$row}", $kpi['arang'], $kpi['format']);
            $sheet->setCellValue("E{$row}", $kpi['note']);
            $sheet->getStyle("E{$row}")->getFont()->setSize(9)->getColor()->setRGB('64748B');
            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        $this->applyTableBorders($sheet, "A{$tableHeaderRow}:E{$row}");

        // SECTION 2: PERFORMA PENJUALAN HARIAN
        $row += 2;
        $sheet->setCellValue("A{$row}", 'II. TREN PENJUALAN HARIAN');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::THEME_SLATE);

        $row++;
        $dailyHeaderRow = $row;
        $sheet->setCellValue("A{$row}", 'Tanggal');
        $sheet->setCellValue("B{$row}", 'Transaksi Gabungan');
        $sheet->setCellValue("C{$row}", 'Omzet Toko (Rp)');
        $sheet->setCellValue("D{$row}", 'Omzet Arang (Rp)');
        $sheet->setCellValue("E{$row}", 'Total Omzet Harian (Rp)');

        $this->styleTableHeader($sheet, "A{$row}:E{$row}", self::THEME_SLATE);

        $startDailyDataRow = $row + 1;
        $dailyList = is_array($daily) ? $daily : $daily->all();

        if (empty($dailyList)) {
            $row++;
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data transaksi pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            foreach ($dailyList as $d) {
                $row++;
                $tgl = is_array($d) ? ($d['d'] ?? '') : ($d->d ?? '');
                $trx = is_array($d) ? ($d['trx'] ?? 0) : ($d->trx ?? 0);
                $tokoOmzet = is_array($d) ? ($d['toko_omzet'] ?? 0) : ($d->toko_omzet ?? 0);
                $arangOmzet = is_array($d) ? ($d['arang_omzet'] ?? 0) : ($d->arang_omzet ?? 0);
                $omzet = is_array($d) ? ($d['omzet'] ?? 0) : ($d->omzet ?? 0);

                $sheet->setCellValue("A{$row}", $tgl);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->setFormattedValue($sheet, "B{$row}", $trx, 'number');
                $this->setFormattedValue($sheet, "C{$row}", $tokoOmzet, 'currency');
                $this->setFormattedValue($sheet, "D{$row}", $arangOmzet, 'currency');
                $this->setFormattedValue($sheet, "E{$row}", $omzet, 'currency', true);
                $sheet->getRowDimension($row)->setRowHeight(19);
            }

            // Total row for daily
            $row++;
            $sheet->setCellValue("A{$row}", 'TOTAL PERIODE');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue("B{$row}", "=SUM(B{$startDailyDataRow}:B" . ($row - 1) . ')');
            $sheet->setCellValue("C{$row}", "=SUM(C{$startDailyDataRow}:C" . ($row - 1) . ')');
            $sheet->setCellValue("D{$row}", "=SUM(D{$startDailyDataRow}:D" . ($row - 1) . ')');
            $sheet->setCellValue("E{$row}", "=SUM(E{$startDailyDataRow}:E" . ($row - 1) . ')');

            $this->applyCurrencyFormat($sheet, "C{$row}:E{$row}");
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $this->styleTotalRow($sheet, "A{$row}:E{$row}");
        }

        $this->applyTableBorders($sheet, "A{$dailyHeaderRow}:E{$row}");

        // SECTION 3: TOP 10 PRODUK TERLARIS
        $row += 2;
        $sheet->setCellValue("A{$row}", 'III. 10 PRODUK & VARIAN PALING LARIS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::THEME_SLATE);

        $row++;
        $topHeaderRow = $row;
        $sheet->setCellValue("A{$row}", 'Peringkat');
        $sheet->setCellValue("B{$row}", 'Nama Produk / Varian');
        $sheet->setCellValue("C{$row}", 'Volume Terjual');
        $sheet->setCellValue("D{$row}", 'Satuan');
        $sheet->setCellValue("E{$row}", 'Total Omzet (Rp)');

        $this->styleTableHeader($sheet, "A{$row}:E{$row}", self::THEME_SLATE);

        $startTopDataRow = $row + 1;
        if (empty($topProducts)) {
            $row++;
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data produk terjual pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            $rank = 1;
            foreach ($topProducts as $tp) {
                $row++;
                $sheet->setCellValue("A{$row}", "#{$rank}");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue("B{$row}", $tp['name'] ?? '-');
                $sheet->setCellValue("C{$row}", (float) ($tp['qty'] ?? 0));
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.##');
                $sheet->setCellValue("D{$row}", $tp['unit'] ?? 'pcs');
                $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->setFormattedValue($sheet, "E{$row}", $tp['omzet'] ?? 0, 'currency', true);
                $sheet->getRowDimension($row)->setRowHeight(19);
                $rank++;
            }

            // Total row for top products
            $row++;
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", 'TOTAL KONTRIBUSI TOP 10');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValue("E{$row}", "=SUM(E{$startTopDataRow}:E" . ($row - 1) . ')');
            $this->applyCurrencyFormat($sheet, "E{$row}");
            $this->styleTotalRow($sheet, "A{$row}:E{$row}");
        }

        $this->applyTableBorders($sheet, "A{$topHeaderRow}:E{$row}");

        $this->autoFitColumns($sheet, range('A', 'E'));
    }

    private function buildSheetToko(Worksheet $sheet, string $storeName, CarbonInterface $from, CarbonInterface $to, $sales): void
    {
        $this->applyHeaderTitle($sheet, $storeName, 'DETAIL TRANSAKSI PENJUALAN TOKO ECERAN', $from, $to, 'J');

        $sheet->freezePane('A6');

        $row = 5;
        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'No.');
        $sheet->setCellValue("B{$row}", 'No. Nota');
        $sheet->setCellValue("C{$row}", 'Waktu Transaksi');
        $sheet->setCellValue("D{$row}", 'Kasir');
        $sheet->setCellValue("E{$row}", 'Pelanggan');
        $sheet->setCellValue("F{$row}", 'Metode Bayar');
        $sheet->setCellValue("G{$row}", 'Status');
        $sheet->setCellValue("H{$row}", 'Subtotal (Rp)');
        $sheet->setCellValue("I{$row}", 'Diskon (Rp)');
        $sheet->setCellValue("J{$row}", 'Total Penjualan (Rp)');

        $this->styleTableHeader($sheet, "A{$row}:J{$row}", self::THEME_DARK);

        $startRow = $row + 1;
        $no = 1;

        if ($sales->isEmpty()) {
            $row++;
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'Belum ada transaksi penjualan toko pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(24);
        } else {
            foreach ($sales as $s) {
                $row++;
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("B{$row}", $s->invoice_no);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("C{$row}", $s->created_at ? $s->created_at->format('d/m/Y H:i') : '-');
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("D{$row}", $s->user?->name ?? '-');
                $sheet->setCellValue("E{$row}", $s->customer?->name ?? 'Umum');

                $sheet->setCellValue("F{$row}", strtoupper($s->payment_type ?? 'TUNAI'));
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $statusLabel = $s->status === 'lunas' ? 'Lunas' : 'Belum Lunas';
                $sheet->setCellValue("G{$row}", $statusLabel);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($s->status !== 'lunas') {
                    $sheet->getStyle("G{$row}")->getFont()->getColor()->setRGB('DC2626')->setBold(true);
                }

                $sheet->setCellValue("H{$row}", (int) $s->subtotal);
                $sheet->setCellValue("I{$row}", (int) $s->discount);
                $sheet->setCellValue("J{$row}", (int) $s->total);

                $this->applyCurrencyFormat($sheet, "H{$row}:J{$row}");
                $sheet->getRowDimension($row)->setRowHeight(19);
            }

            // Total Row
            $row++;
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'TOTAL PENJUALAN TOKO ECERAN');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue("H{$row}", "=SUM(H{$startRow}:H" . ($row - 1) . ')');
            $sheet->setCellValue("I{$row}", "=SUM(I{$startRow}:I" . ($row - 1) . ')');
            $sheet->setCellValue("J{$row}", "=SUM(J{$startRow}:J" . ($row - 1) . ')');

            $this->applyCurrencyFormat($sheet, "H{$row}:J{$row}");
            $this->styleTotalRow($sheet, "A{$row}:J{$row}");
        }

        $this->applyTableBorders($sheet, "A{$headerRow}:J{$row}");
        $this->autoFitColumns($sheet, range('A', 'J'));
    }

    private function buildSheetArangJual(Worksheet $sheet, string $storeName, CarbonInterface $from, CarbonInterface $to, $arangJuals): void
    {
        $this->applyHeaderTitle($sheet, $storeName, 'DETAIL PENJUALAN ARANG KILOAN (GROSIR & ECERAN)', $from, $to, 'M');

        $sheet->freezePane('A6');

        $row = 5;
        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'No.');
        $sheet->setCellValue("B{$row}", 'No. Nota');
        $sheet->setCellValue("C{$row}", 'Waktu Transaksi');
        $sheet->setCellValue("D{$row}", 'Kasir');
        $sheet->setCellValue("E{$row}", 'Pembeli / Pelanggan');
        $sheet->setCellValue("F{$row}", 'Varian Arang');
        $sheet->setCellValue("G{$row}", 'Berat (kg)');
        $sheet->setCellValue("H{$row}", 'Harga / kg (Rp)');
        $sheet->setCellValue("I{$row}", 'Diskon (Rp)');
        $sheet->setCellValue("J{$row}", 'Grand Total (Rp)');
        $sheet->setCellValue("K{$row}", 'Metode Bayar');
        $sheet->setCellValue("L{$row}", 'Status');
        $sheet->setCellValue("M{$row}", 'Catatan');

        $this->styleTableHeader($sheet, "A{$row}:M{$row}", self::THEME_DARK);

        $startRow = $row + 1;
        $no = 1;

        if ($arangJuals->isEmpty()) {
            $row++;
            $sheet->mergeCells("A{$row}:M{$row}");
            $sheet->setCellValue("A{$row}", 'Belum ada transaksi penjualan arang pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(24);
        } else {
            foreach ($arangJuals as $aj) {
                $row++;
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("B{$row}", $aj->no_nota);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("C{$row}", $aj->created_at ? $aj->created_at->format('d/m/Y H:i') : '-');
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("D{$row}", $aj->user?->name ?? '-');
                $pembeli = $aj->customer?->name ?? ($aj->nama_pembeli ?: 'Umum');
                $sheet->setCellValue("E{$row}", $pembeli);

                $sheet->setCellValue("F{$row}", $aj->arangJenis?->nama ?? 'Arang');

                $sheet->setCellValue("G{$row}", (float) $aj->berat_kg);
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->setCellValue("H{$row}", (int) $aj->harga_jual_per_kg);
                $sheet->setCellValue("I{$row}", (int) $aj->diskon);
                $sheet->setCellValue("J{$row}", (int) $aj->grand_total);

                $this->applyCurrencyFormat($sheet, "H{$row}:J{$row}");

                $sheet->setCellValue("K{$row}", strtoupper($aj->payment_type ?? 'TUNAI'));
                $sheet->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $statusLabel = $aj->status === 'lunas' ? 'Lunas' : 'Belum Lunas';
                $sheet->setCellValue("L{$row}", $statusLabel);
                $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($aj->status !== 'lunas') {
                    $sheet->getStyle("L{$row}")->getFont()->getColor()->setRGB('DC2626')->setBold(true);
                }

                $sheet->setCellValue("M{$row}", $aj->catatan ?? '-');
                $sheet->getRowDimension($row)->setRowHeight(19);
            }

            // Total Row
            $row++;
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", 'TOTAL PENJUALAN ARANG KILOAN');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue("G{$row}", "=SUM(G{$startRow}:G" . ($row - 1) . ')');
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            $sheet->setCellValue("H{$row}", '-');
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue("I{$row}", "=SUM(I{$startRow}:I" . ($row - 1) . ')');
            $sheet->setCellValue("J{$row}", "=SUM(J{$startRow}:J" . ($row - 1) . ')');

            $this->applyCurrencyFormat($sheet, "I{$row}:J{$row}");
            $this->styleTotalRow($sheet, "A{$row}:M{$row}");
        }

        $this->applyTableBorders($sheet, "A{$headerRow}:M{$row}");
        $this->autoFitColumns($sheet, range('A', 'M'));
    }

    private function buildSheetArangBeli(Worksheet $sheet, string $storeName, CarbonInterface $from, CarbonInterface $to, $arangBelis): void
    {
        $this->applyHeaderTitle($sheet, $storeName, 'DETAIL PEMBELIAN STOK ARANG DARI PEMBUAT / PEMASOK', $from, $to, 'J');

        $sheet->freezePane('A6');

        $row = 5;
        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'No.');
        $sheet->setCellValue("B{$row}", 'No. Bukti');
        $sheet->setCellValue("C{$row}", 'Waktu Pembelian');
        $sheet->setCellValue("D{$row}", 'Penerima / Kasir');
        $sheet->setCellValue("E{$row}", 'Nama Pembuat / Pemasok');
        $sheet->setCellValue("F{$row}", 'Varian Arang');
        $sheet->setCellValue("G{$row}", 'Berat (kg)');
        $sheet->setCellValue("H{$row}", 'Harga Beli / kg (Rp)');
        $sheet->setCellValue("I{$row}", 'Total Bayar (Rp)');
        $sheet->setCellValue("J{$row}", 'Catatan');

        $this->styleTableHeader($sheet, "A{$row}:J{$row}", self::THEME_DARK);

        $startRow = $row + 1;
        $no = 1;

        if ($arangBelis->isEmpty()) {
            $row++;
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'Belum ada pembelian stok arang pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(24);
        } else {
            foreach ($arangBelis as $ab) {
                $row++;
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $bukti = 'BELI-ARNG-' . str_pad((string) $ab->id, 4, '0', STR_PAD_LEFT);
                $sheet->setCellValue("B{$row}", $bukti);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("C{$row}", $ab->created_at ? $ab->created_at->format('d/m/Y H:i') : '-');
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("D{$row}", $ab->user?->name ?? '-');
                $sheet->setCellValue("E{$row}", $ab->nama_pemasok);
                $sheet->setCellValue("F{$row}", $ab->arangJenis?->nama ?? 'Arang');

                $sheet->setCellValue("G{$row}", (float) $ab->berat_kg);
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->setCellValue("H{$row}", (int) $ab->harga_beli_per_kg);
                $sheet->setCellValue("I{$row}", (int) $ab->total_harga);

                $this->applyCurrencyFormat($sheet, "H{$row}:I{$row}");
                $sheet->setCellValue("J{$row}", $ab->catatan ?? '-');
                $sheet->getRowDimension($row)->setRowHeight(19);
            }

            // Total Row
            $row++;
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", 'TOTAL PEMBELIAN STOK ARANG');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue("G{$row}", "=SUM(G{$startRow}:G" . ($row - 1) . ')');
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            $sheet->setCellValue("H{$row}", '-');
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue("I{$row}", "=SUM(I{$startRow}:I" . ($row - 1) . ')');
            $this->applyCurrencyFormat($sheet, "I{$row}");
            $this->styleTotalRow($sheet, "A{$row}:J{$row}");
        }

        $this->applyTableBorders($sheet, "A{$headerRow}:J{$row}");
        $this->autoFitColumns($sheet, range('A', 'J'));
    }

    private function styleTableHeader(Worksheet $sheet, string $range, string $bgColor): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $bgColor],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $rowNum = (int) filter_var(explode(':', $range)[0], FILTER_SANITIZE_NUMBER_INT);
        $sheet->getRowDimension($rowNum)->setRowHeight(26);
    }

    private function styleTotalRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::BG_TOTAL],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER_COLOR],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => self::THEME_SLATE],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $rowNum = (int) filter_var(explode(':', $range)[0], FILTER_SANITIZE_NUMBER_INT);
        $sheet->getRowDimension($rowNum)->setRowHeight(22);
    }

    private function applyTableBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER_COLOR],
                ],
            ],
        ]);
    }

    private function applyCurrencyFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function setFormattedValue(Worksheet $sheet, string $cell, $value, string $format, bool $bold = false): void
    {
        if ($value === '-' || $value === null) {
            $sheet->setCellValue($cell, '-');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }

        if ($format === 'currency') {
            $sheet->setCellValue($cell, (float) $value);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        } elseif ($format === 'kg') {
            $sheet->setCellValue($cell, (float) $value);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.0" kg"');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        } elseif ($format === 'number') {
            $sheet->setCellValue($cell, (int) $value);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        } else {
            $sheet->setCellValue($cell, $value);
        }

        if ($bold) {
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
    }

    private function autoFitColumns(Worksheet $sheet, array $cols): void
    {
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
