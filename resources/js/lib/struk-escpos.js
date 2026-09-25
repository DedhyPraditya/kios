import { rupiah, tanggal } from "@/lib/format";

/* Perintah ESC/POS untuk RPP02N: kertas 58 mm, area cetak 48 mm, yang pada
   huruf A (12 titik) muat 32 karakter per baris. Dipakai jalur Bluetooth di
   ponsel — di sana tak ada dialog cetak, jadi tata letaknya disusun sendiri
   sebagai teks, bukan diserahkan ke mesin cetak peramban. */
const LEBAR = 32;
/* Lebar bidang cetak dalam titik (48 mm pada 8 titik/mm). */
const TITIK = 384;

const ESC = 0x1b;
const GS = 0x1d;
const LF = 0x0a;

/* Tabel karakter bawaan printer hanya ASCII. Tanda tipografi yang dipakai di
   tampilan layar (× − ·) diganti padanannya; sisa karakter di luar ASCII
   dibuang, sebab kalau dikirim apa adanya keluar jadi simbol acak. */
const GANTI = {
    "×": "x",
    "−": "-",
    "–": "-",
    "—": "-",
    "·": ".",
    "…": "...",
    "’": "'",
    "‘": "'",
    "“": '"',
    "”": '"',
};

function ascii(teks) {
    return String(teks ?? "")
        .replace(/[×−–—·…’‘“”]/g, (c) => GANTI[c])
        .replace(/[^\x20-\x7e]/g, "");
}

/* Dua kolom kiri-kanan dalam satu baris selebar kertas. Kalau isinya tak muat,
   yang dipotong bagian kirinya — angka di kanan justru yang wajib terbaca. */
function duaKolom(kiri, kanan) {
    const kn = ascii(kanan);
    let kr = ascii(kiri);

    if (kr.length + kn.length + 1 > LEBAR) {
        kr = kr.slice(0, Math.max(0, LEBAR - kn.length - 1));
    }

    return kr + " ".repeat(Math.max(1, LEBAR - kr.length - kn.length)) + kn;
}

function bungkus(teks, lebar = LEBAR) {
    const kata = ascii(teks).split(/\s+/).filter(Boolean);
    const baris = [];
    let kini = "";

    for (const k of kata) {
        if (!kini.length) {
            kini = k;
        } else if (kini.length + 1 + k.length <= lebar) {
            kini += " " + k;
        } else {
            baris.push(kini);
            kini = k;
        }

        while (kini.length > lebar) {
            baris.push(kini.slice(0, lebar));
            kini = kini.slice(lebar);
        }
    }

    if (kini.length) baris.push(kini);

    return baris.length ? baris : [""];
}

class Pita {
    constructor() {
        this.isi = [];
    }

    perintah(...bytes) {
        this.isi.push(...bytes);
        return this;
    }

    teks(t) {
        for (const c of ascii(t)) this.isi.push(c.charCodeAt(0));
        return this;
    }

    baris(t = "") {
        return this.teks(t).perintah(LF);
    }

    tebal(nyala) {
        return this.perintah(ESC, 0x45, nyala ? 1 : 0);
    }

    /* GS ! mengatur perbesaran: nibble atas lebar, nibble bawah tinggi. Huruf
       ganda memakai dua kolom per karakter, jadi barisnya cuma muat separuh. */
    besar(nyala) {
        return this.perintah(GS, 0x21, nyala ? 0x11 : 0x00);
    }

    /* Perataan diserahkan ke printer, bukan disulap dengan spasi: spasi salah
       hitung begitu ukuran huruf berubah. */
    tengah() {
        return this.perintah(ESC, 0x61, 1);
    }

    kiri() {
        return this.perintah(ESC, 0x61, 0);
    }

    garis() {
        return this.baris("-".repeat(LEBAR));
    }

    /* QR bawaan printer (GS ( k, model 2, koreksi M). */
    qr(data) {
        const isi = [...ascii(data)].map((c) => c.charCodeAt(0));
        const n = isi.length + 3;
        return this.perintah(GS, 0x28, 0x6b, 4, 0, 0x31, 0x41, 0x32, 0x00)
            .perintah(GS, 0x28, 0x6b, 3, 0, 0x31, 0x43, 6)
            .perintah(GS, 0x28, 0x6b, 3, 0, 0x31, 0x45, 0x31)
            .perintah(GS, 0x28, 0x6b, n & 0xff, n >> 8, 0x31, 0x50, 0x30, ...isi)
            .perintah(GS, 0x28, 0x6b, 3, 0, 0x31, 0x51, 0x30)
            .perintah(LF);
    }

    /* Barcode CODE128 bawaan printer (GS k 73). Deret angka genap >= 4 dikemas
       set C (dua angka per simbol) supaya nomor nota muat di kertas 58 mm;
       modul dipersempit bila tetap kepanjangan. */
    barcode(data) {
        const isi = [];
        let simbol = 2; // start + check
        let set = null;
        const teks = ascii(data);
        for (let i = 0; i < teks.length; ) {
            const angka = /^\d+/.exec(teks.slice(i))?.[0] ?? "";
            const panjangC = angka.length - (angka.length % 2);
            if (panjangC >= 4) {
                if (set !== "C") isi.push(0x7b, 0x43), (set = "C"), simbol++;
                for (let j = 0; j < panjangC; j += 2) isi.push(Number(angka.slice(j, j + 2))), simbol++;
                i += panjangC;
            } else {
                if (set !== "B") isi.push(0x7b, 0x42), (set = "B"), simbol++;
                const c = teks.charCodeAt(i);
                isi.push(...(c === 0x7b ? [0x7b, 0x7b] : [c])), simbol++;
                i++;
            }
        }
        simbol--; // simbol start sudah dihitung di awal
        const modul = simbol * 11 + 13;
        const lebarModul = modul * 2 <= TITIK ? 2 : 1;

        return this.perintah(GS, 0x68, 60) // tinggi 60 titik
            .perintah(GS, 0x77, lebarModul)
            .perintah(GS, 0x48, 0) // teks nomor dicetak terpisah
            .perintah(GS, 0x6b, 73, isi.length, ...isi)
            .perintah(LF);
    }

    /* Kode nomor nota sesuai Pengaturan (none | qr | barcode). */
    kodeNota(jenis, nomor) {
        if (jenis !== "qr" && jenis !== "barcode") return this;
        this.tengah().baris();
        jenis === "qr" ? this.qr(nomor) : this.barcode(nomor);
        return this.baris(nomor).kiri();
    }

    selesai() {
        return new Uint8Array(this.isi);
    }
}

export function strukEscPos(sale, store) {
    const kasbon = sale.payment_type === "kasbon";
    const lunas = sale.status === "lunas";
    const p = new Pita();

    p.perintah(ESC, 0x40); // inisialisasi: buang setelan sisa cetakan sebelumnya

    p.tengah();

    p.besar(true).tebal(true);
    for (const b of bungkus(store.store_name, LEBAR / 2)) p.baris(b);
    p.besar(false).tebal(false);

    if (store.store_address) {
        for (const b of bungkus(store.store_address)) p.baris(b);
    }

    if (store.store_phone) p.baris(store.store_phone);

    if (sale.voided) {
        p.baris();
        p.tebal(true).baris("** NOTA DIBATALKAN **").tebal(false);
    }

    p.kiri();
    p.garis();
    p.baris(duaKolom("No", sale.invoice_no));
    p.baris(duaKolom("Waktu", tanggal(sale.created_at)));
    p.baris(duaKolom("Kasir", sale.user?.name ?? "-"));
    if (kasbon) p.baris(duaKolom("Pelanggan", sale.customer?.name ?? "-"));
    p.baris(
        duaKolom(
            "Metode",
            kasbon ? "KASBON" : sale.payment_type === "qris" ? "QRIS" : "TUNAI",
        ),
    );

    p.garis();
    for (const it of sale.items ?? []) {
        for (const b of bungkus(it.name)) p.baris(b);
        const satuan = it.unit_name ? ` ${it.unit_name}` : "";
        const kotor = it.price * it.qty;
        p.baris(duaKolom(`  ${it.qty}${satuan} x ${rupiah(it.price)}`, rupiah(kotor)));
        if (it.discount) p.baris(duaKolom("  Diskon", "-" + rupiah(it.discount)));
    }

    p.garis();
    p.baris(duaKolom("Subtotal", rupiah(sale.subtotal)));
    if (sale.discount) {
        const persen = sale.discount_percent ? ` ${Number(sale.discount_percent)}%` : "";
        p.baris(duaKolom(`Diskon${persen}`, "-" + rupiah(sale.discount)));
    }
    if (sale.tax) p.baris(duaKolom(`PPN ${Number(sale.tax_rate)}%`, rupiah(sale.tax)));

    p.tebal(true)
        .baris(duaKolom("TOTAL", rupiah(sale.total)))
        .tebal(false);

    if (kasbon) {
        p.baris(duaKolom("DP", rupiah(sale.paid)));
        p.tebal(true)
            .baris(duaKolom("Sisa hutang", rupiah(sale.outstanding)))
            .tebal(false);
        if (sale.due_date) p.baris(duaKolom("Jatuh tempo", sale.due_date));

        p.baris();
        p.tengah()
            .tebal(true)
            .baris(lunas ? "== LUNAS ==" : "== BELUM LUNAS ==")
            .tebal(false)
            .kiri();
    } else {
        p.baris(duaKolom("Bayar", rupiah(sale.paid)));
        p.baris(duaKolom("Kembali", rupiah(sale.change)));
    }

    if (sale.note) {
        p.baris();
        for (const b of bungkus("Catatan: " + sale.note)) p.baris(b);
    }

    p.kodeNota(store.receipt_code, sale.invoice_no);

    p.tengah();

    if (store.receipt_footer) {
        p.baris();
        for (const b of bungkus(store.receipt_footer)) p.baris(b);
    }

    p.baris("... SIMPAN STRUK INI ...");
    p.kiri();

    /* RPP02N tak berpisau, jadi tak ada perintah potong. Empat baris kosong
       memberi ruang sobek supaya baris terakhir tak tertinggal di dalam. */
    p.perintah(LF, LF, LF, LF);

    return p.selesai();
}

export function strukPenjualanArangEscPos(penjualan, store) {
    const kasbon = penjualan.payment_type === "kasbon";
    const lunas = penjualan.status === "lunas";
    const p = new Pita();

    p.perintah(ESC, 0x40);

    p.tengah();
    p.besar(true).tebal(true);
    for (const b of bungkus(store.store_name || "Kios BERKAH", LEBAR / 2))
        p.baris(b);
    p.besar(false).tebal(false);

    if (store.store_address) {
        for (const b of bungkus(store.store_address)) p.baris(b);
    }
    if (store.store_phone) p.baris(store.store_phone);

    p.baris();
    p.tebal(true).baris("STRUK PENJUALAN ARANG").tebal(false);

    p.kiri();
    p.garis();
    p.baris(duaKolom("No. Nota", penjualan.no_nota));
    p.baris(duaKolom("Waktu", tanggal(penjualan.created_at)));
    p.baris(duaKolom("Kasir", penjualan.user?.name ?? "-"));
    p.baris(
        duaKolom(
            "Pembeli",
            penjualan.customer?.name ?? (penjualan.nama_pembeli || "Umum"),
        ),
    );
    p.baris(
        duaKolom(
            "Metode",
            kasbon
                ? "KASBON"
                : penjualan.payment_type === "qris"
                  ? "QRIS"
                  : "TUNAI",
        ),
    );

    p.garis();
    const namaJenis = penjualan.arang_jenis?.nama || "Arang Kiloan";
    for (const b of bungkus(namaJenis)) p.baris(b);
    p.baris(
        duaKolom(
            `  ${penjualan.berat_kg} kg x ${rupiah(penjualan.harga_jual_per_kg)}`,
            rupiah(penjualan.total_harga),
        ),
    );

    p.garis();
    p.baris(duaKolom("Subtotal", rupiah(penjualan.total_harga)));
    if (penjualan.diskon > 0) {
        p.baris(duaKolom("Diskon", "-" + rupiah(penjualan.diskon)));
    }

    p.tebal(true)
        .baris(duaKolom("TOTAL", rupiah(penjualan.grand_total)))
        .tebal(false);

    if (kasbon) {
        p.baris(duaKolom("DP Diterima", rupiah(penjualan.paid)));
        const sisa = Math.max(0, penjualan.grand_total - (penjualan.paid || 0));
        p.tebal(true)
            .baris(duaKolom("Sisa Kasbon", rupiah(sisa)))
            .tebal(false);
        p.baris();
        p.tengah()
            .tebal(true)
            .baris(lunas ? "== LUNAS ==" : "== BELUM LUNAS ==")
            .tebal(false)
            .kiri();
    } else if (penjualan.payment_type === "qris") {
        p.tengah().tebal(true).baris("== LUNAS (QRIS) ==").tebal(false).kiri();
    } else {
        p.baris(duaKolom("Bayar", rupiah(penjualan.paid)));
        p.baris(duaKolom("Kembali", rupiah(penjualan.change)));
    }

    if (penjualan.catatan) {
        p.baris();
        for (const b of bungkus("Catatan: " + penjualan.catatan)) p.baris(b);
    }

    p.kodeNota(store.receipt_code, penjualan.no_nota);

    p.tengah();
    p.baris();
    p.baris("Terima kasih atas pembelian Anda!");
    p.baris("... SIMPAN STRUK INI ...");
    p.kiri();

    p.perintah(LF, LF, LF, LF);
    return p.selesai();
}

export function notaPembelianArangEscPos(pembelian, store) {
    const p = new Pita();

    p.perintah(ESC, 0x40);

    p.tengah();
    p.besar(true).tebal(true);
    for (const b of bungkus(store.store_name || "Kios BERKAH", LEBAR / 2))
        p.baris(b);
    p.besar(false).tebal(false);

    if (store.store_address) {
        for (const b of bungkus(store.store_address)) p.baris(b);
    }
    if (store.store_phone) p.baris(store.store_phone);

    p.baris();
    p.tebal(true).baris("NOTA PEMBELIAN ARANG").tebal(false);

    p.kiri();
    p.garis();
    p.baris(duaKolom("No. Nota", pembelian.no_nota));
    p.baris(duaKolom("Waktu", tanggal(pembelian.created_at)));
    p.baris(duaKolom("Pemasok", pembelian.nama_pemasok));
    p.baris(duaKolom("Penerima", pembelian.user?.name ?? "-"));

    p.garis();
    const namaJenis = pembelian.arang_jenis?.nama || "Arang Kiloan";
    for (const b of bungkus(namaJenis)) p.baris(b);
    p.baris(
        duaKolom(
            `  ${pembelian.berat_kg} kg x ${rupiah(pembelian.harga_beli_per_kg)}`,
            rupiah(pembelian.total_harga),
        ),
    );

    p.garis();
    p.tebal(true)
        .baris(duaKolom("DIBAYAR TUNAI", rupiah(pembelian.total_harga)))
        .tebal(false);

    if (pembelian.catatan) {
        p.baris();
        for (const b of bungkus("Catatan: " + pembelian.catatan)) p.baris(b);
    }

    p.tengah();
    p.baris();
    p.baris("== LUNAS DI TEMPAT ==");
    p.kiri();

    p.perintah(LF, LF, LF, LF);
    return p.selesai();
}
