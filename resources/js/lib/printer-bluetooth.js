/* Jalur cetak untuk ponsel: kirim perintah ESC/POS langsung ke printer lewat
   Web Bluetooth, tanpa dialog cetak. Dipakai karena kotak cetak Android sulit
   diandalkan untuk kertas 58 mm, dan Safari iOS tak mengenal printer non-AirPrint
   sama sekali (di iPhone, halaman ini harus dibuka lewat peramban Bluefy).

   Sambungan disimpan selama sesi peramban hidup. Karena aplikasi ini Inertia —
   pindah halaman tak memuat ulang berkas JS — kasir hanya memilih printer sekali
   di awal, bukan tiap transaksi. */

/* Printer thermal murah memakai UUID layanan yang berbeda-beda. Semua yang
   umum didaftarkan di sini karena Web Bluetooth menolak akses ke layanan yang
   tak disebut lebih dulu saat meminta perangkat. */
const LAYANAN = [
    0x18f0, // Rongta, Goojprt, dan sebagian besar RPP02N
    0xff00,
    0xffe0,
    0xfee7,
    "49535343-fe7d-4ae5-8fa9-9fafd205e455", // modul Microchip/ISSC
    "e7810a71-73ae-499d-8c15-faa9aef0c3f2",
];

let perangkat = null;
let jalurTulis = null;

export function didukung() {
    return typeof navigator !== "undefined" && !!navigator.bluetooth;
}

function jeda(ms) {
    return new Promise((selesai) => setTimeout(selesai, ms));
}

function lupakan() {
    perangkat = null;
    jalurTulis = null;
}

async function pilihPerangkat() {
    /* Perangkat yang izinnya sudah pernah diberikan bisa dipakai lagi tanpa
       memunculkan daftar — tidak semua peramban mendukungnya, jadi dibungkus. */
    if (navigator.bluetooth.getDevices) {
        try {
            const [pernah] = await navigator.bluetooth.getDevices();
            if (pernah) return pernah;
        } catch {
            // abaikan: jatuh ke pemilihan manual di bawah
        }
    }

    return navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: LAYANAN,
    });
}

async function cariJalurTulis(server) {
    for (const layanan of await server.getPrimaryServices()) {
        for (const k of await layanan.getCharacteristics()) {
            if (k.properties.write || k.properties.writeWithoutResponse) {
                return k;
            }
        }
    }

    throw new Error(
        "Printer tersambung, tapi tak punya jalur tulis yang dikenali. " +
            "Kemungkinan modelnya memakai layanan Bluetooth di luar daftar.",
    );
}

async function sambung() {
    if (jalurTulis && perangkat?.gatt?.connected) return jalurTulis;

    perangkat = await pilihPerangkat();
    perangkat.addEventListener("gattserverdisconnected", lupakan);

    let server;
    try {
        server = await perangkat.gatt.connect();
    } catch (e) {
        /* Perangkat yang diingat izinnya bisa saja sedang mati atau jauh.
           Sekali lagi lewat daftar pilihan, baru menyerah. */
        lupakan();
        perangkat = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: LAYANAN,
        });
        perangkat.addEventListener("gattserverdisconnected", lupakan);
        server = await perangkat.gatt.connect();
    }

    jalurTulis = await cariJalurTulis(server);

    return jalurTulis;
}

export async function cetakKeBluetooth(bytes) {
    const jalur = await sambung();

    /* Paket BLE bawaan hanya 20 byte. Chrome biasanya menaikkannya, tapi kalau
       potongan besar ditolak, sekali coba ulang dengan ukuran aman. */
    for (const potong of [180, 20]) {
        try {
            for (let i = 0; i < bytes.length; i += potong) {
                const bagian = bytes.slice(i, i + potong);

                if (jalur.properties.writeWithoutResponse) {
                    await jalur.writeValueWithoutResponse(bagian);
                } else {
                    await jalur.writeValue(bagian);
                }

                /* Penyangga printer kecil; tanpa jeda, baris belakang hilang. */
                await jeda(20);
            }

            return;
        } catch (e) {
            if (potong === 20) throw e;
        }
    }
}

export function namaPrinter() {
    return perangkat?.name ?? null;
}
