@php
    $rp = fn ($n) => 'Rp'.number_format((int) $n, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $storeName }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1e293b; }
        h1 { font-size: 16px; margin: 0; color: #0f172a; }
        h2 { font-size: 11px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1.5px solid #0f766e; color: #0f766e; }
        .muted { color: #64748b; }
        .kop { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 4px; }
        .kop p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 0.6px solid #cbd5e1; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: bold; font-size: 9px; }
        .r { text-align: right; }
        .total td { font-weight: bold; background: #f8fafc; border-top: 1px solid #94a3b8; }
        .ringkas td { font-size: 10.5px; padding: 6px; }
        .ringkas td.label { width: 34%; }
        .note { margin-top: 6px; padding: 5px 8px; background: #fef3c7; border: 0.6px solid #f59e0b; }
        .kosong { padding: 8px 6px; color: #94a3b8; font-style: italic; }
        .kaki { margin-top: 18px; font-size: 8.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $storeName }}</h1>
        @if (! empty($store['store_address']))
            <p class="muted">{{ $store['store_address'] }}{{ ! empty($store['store_phone']) ? ' · '.$store['store_phone'] : '' }}</p>
        @endif
        <p><strong>Laporan Penjualan &amp; Keuangan</strong> · Periode {{ $from->format('d/m/Y') }} s/d {{ $to->format('d/m/Y') }}</p>
        @if ($filterNote)
            <p>Filter: {{ $filterNote }}</p>
        @endif
    </div>

    <h2>Ringkasan</h2>
    <table class="ringkas">
        <tr><th></th><th class="r">Gabungan</th><th class="r">Toko Eceran</th><th class="r">Arang</th></tr>
        <tr><td class="label">Omzet</td><td class="r">{{ $rp($summary['omzet']) }}</td><td class="r">{{ $rp($breakdown['toko']['omzet']) }}</td><td class="r">{{ $rp($breakdown['arang']['omzet']) }}</td></tr>
        <tr><td class="label">Laba kotor (perkiraan)</td><td class="r">{{ $rp($summary['profit']) }}</td><td class="r">{{ $rp($breakdown['toko']['profit']) }}</td><td class="r">{{ $rp($breakdown['arang']['profit']) }}</td></tr>
        <tr><td class="label">Jumlah transaksi</td><td class="r">{{ $summary['count'] }}</td><td class="r">{{ $breakdown['toko']['count'] }}</td><td class="r">{{ $breakdown['arang']['count'] }}</td></tr>
        <tr><td class="label">Diskon diberikan</td><td class="r">{{ $rp($summary['discount']) }}</td><td class="r">{{ $rp($breakdown['toko']['discount']) }}</td><td class="r">{{ $rp($breakdown['arang']['discount']) }}</td></tr>
        @if ($summary['tax'] > 0)
            <tr><td class="label">PPN dipungut (termasuk di omzet)</td><td class="r">{{ $rp($summary['tax']) }}</td><td class="r">{{ $rp($summary['tax']) }}</td><td class="r">-</td></tr>
        @endif
        <tr><td class="label">Retur barang</td><td class="r">{{ $rp($summary['refunded']) }}</td><td class="r">{{ $rp($summary['refunded']) }}</td><td class="r">-</td></tr>
        <tr><td class="label">Arang terjual / beli stok arang</td><td class="r">-</td><td class="r">-</td><td class="r">{{ $breakdown['arang']['berat_kg'] }} kg / {{ $rp($breakdown['arang']['beli_stok']) }}</td></tr>
        <tr><td class="label">Sisa piutang saat ini (semua)</td><td class="r">{{ $rp($piutang['total']) }}</td><td class="r">{{ $rp($piutang['toko']) }}</td><td class="r">{{ $rp($piutang['arang']) }}</td></tr>
    </table>
    @if ($kategoriAktif)
        <p class="note">Filter kategori: angka hanya dari barang toko pada kategori ini, dihitung dari harga barang setelah diskon per barang, sebelum diskon nota dan PPN. Penjualan arang tidak ikut dihitung.</p>
    @endif

    <h2>Omzet Harian</h2>
    <table>
        <tr><th>Tanggal</th><th class="r">Transaksi</th><th class="r">Toko</th><th class="r">Arang</th><th class="r">Total</th></tr>
        @forelse ($daily as $d)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($d['d'])->format('d/m/Y') }}</td>
                <td class="r">{{ $d['trx'] }}</td>
                <td class="r">{{ $rp($d['toko_omzet']) }}</td>
                <td class="r">{{ $rp($d['arang_omzet']) }}</td>
                <td class="r">{{ $rp($d['omzet']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="kosong">Tidak ada transaksi pada periode ini.</td></tr>
        @endforelse
    </table>

    <h2>Produk Terlaris</h2>
    <table>
        <tr><th>#</th><th>Produk</th><th class="r">Terjual</th><th class="r">Omzet</th></tr>
        @forelse ($topProducts as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p['name'] }}</td>
                <td class="r">{{ $p['qty'] }} {{ $p['unit'] }}</td>
                <td class="r">{{ $rp($p['omzet']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="kosong">Belum ada penjualan.</td></tr>
        @endforelse
    </table>

    <h2>Rincian Nota Toko</h2>
    <table>
        <tr><th>No. Nota</th><th>Waktu</th><th>Kasir</th><th>Pelanggan</th><th>Bayar</th><th class="r">Diskon</th><th class="r">Retur</th><th class="r">Total</th></tr>
        @forelse ($sales as $s)
            <tr>
                <td>{{ $s->invoice_no }}</td>
                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $s->user?->name ?? '-' }}</td>
                <td>{{ $s->customer?->name ?? 'Umum' }}</td>
                <td>{{ strtoupper($s->payment_type) }}{{ $s->status === 'belum_lunas' ? ' (belum lunas)' : '' }}</td>
                <td class="r">{{ $rp($s->discount) }}</td>
                <td class="r">{{ $rp($s->refunded) }}</td>
                <td class="r">{{ $rp($s->total - $s->refunded) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="kosong">Tidak ada nota toko.</td></tr>
        @endforelse
        @if ($sales->isNotEmpty())
            <tr class="total"><td colspan="7">Jumlah ({{ $sales->count() }} nota)</td><td class="r">{{ $rp($sales->sum(fn ($s) => $s->total - $s->refunded)) }}</td></tr>
        @endif
    </table>

    @if (! $kategoriAktif)
        <h2>Rincian Penjualan Arang</h2>
        <table>
            <tr><th>No. Nota</th><th>Waktu</th><th>Kasir</th><th>Pembeli</th><th>Jenis</th><th class="r">Berat</th><th class="r">Harga/kg</th><th class="r">Total</th></tr>
            @forelse ($arangJuals as $a)
                <tr>
                    <td>{{ $a->no_nota }}</td>
                    <td>{{ $a->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $a->user?->name ?? '-' }}</td>
                    <td>{{ $a->customer?->name ?? ($a->nama_pembeli ?: 'Umum') }}</td>
                    <td>{{ $a->arangJenis?->nama ?? '-' }}</td>
                    <td class="r">{{ $a->berat_kg }} kg</td>
                    <td class="r">{{ $rp($a->harga_jual_per_kg) }}</td>
                    <td class="r">{{ $rp($a->grand_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="kosong">Tidak ada penjualan arang.</td></tr>
            @endforelse
            @if ($arangJuals->isNotEmpty())
                <tr class="total"><td colspan="7">Jumlah ({{ $arangJuals->count() }} nota)</td><td class="r">{{ $rp($arangJuals->sum('grand_total')) }}</td></tr>
            @endif
        </table>
    @endif

    <p class="kaki">Dicetak {{ now()->format('d/m/Y H:i') }} dari aplikasi kasir {{ $storeName }}.</p>
</body>
</html>
