import { rupiah, tanggal } from "@/lib/format";

/* Perintah ESC/POS untuk RPP02N: kertas 58 mm, area cetak 48 mm, yang pada
   huruf A (12 titik) muat 32 karakter per baris. Dipakai jalur Bluetooth di
   ponsel — di sana tak ada dialog cetak, jadi tata letaknya disusun sendiri
   sebagai teks, bukan diserahkan ke mesin cetak peramban. */
const LEBAR = 32;

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
    p.baris(duaKolom("Metode", kasbon ? "KASBON" : "TUNAI"));

    p.garis();
    for (const it of sale.items ?? []) {
        for (const b of bungkus(it.name)) p.baris(b);
        p.baris(duaKolom(`  ${it.qty} x ${rupiah(it.price)}`, rupiah(it.subtotal)));
    }

    p.garis();
    p.baris(duaKolom("Subtotal", rupiah(sale.subtotal)));
    if (sale.discount) p.baris(duaKolom("Diskon", "-" + rupiah(sale.discount)));

    p.tebal(true).baris(duaKolom("TOTAL", rupiah(sale.total))).tebal(false);

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
