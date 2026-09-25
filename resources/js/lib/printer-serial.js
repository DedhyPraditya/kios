/* Jalur cetak langsung untuk PC: kirim ESC/POS lewat Web Serial (Chrome/Edge
   desktop). Dipakai untuk printer thermal yang di Windows tampil sebagai port
   COM — printer USB dengan chip USB-serial, atau printer Bluetooth klasik yang
   sudah dipasangkan (pairing) di pengaturan Bluetooth Windows.

   Seperti jalur Bluetooth, port disimpan selama halaman hidup; kasir cukup
   memilih port sekali. Izin port juga diingat peramban, jadi setelah muat
   ulang port yang sama dipakai lagi tanpa memilih. */

let port = null;

export function serialDidukung() {
    return typeof navigator !== "undefined" && !!navigator.serial;
}

async function pilihPort() {
    const [pernah] = await navigator.serial.getPorts();
    return pernah ?? navigator.serial.requestPort();
}

async function buka() {
    if (port?.writable) return port;

    port = await pilihPort();
    if (!port.writable) {
        // 9600 baud adalah bawaan hampir semua printer thermal 58 mm.
        await port.open({ baudRate: 9600 });
    }

    return port;
}

export async function cetakKeSerial(bytes) {
    const p = await buka();
    const penulis = p.writable.getWriter();

    try {
        await penulis.write(bytes);
    } finally {
        penulis.releaseLock();
    }
}
