// QRIS dinamis dari QRIS statis toko.
//
// QRIS = teks EMV berformat TLV (tag 2 digit, panjang 2 digit, nilai),
// diakhiri tag 63 berisi CRC16. QRIS statis punya tag 01 = "11" dan tanpa
// nominal; QRIS dinamis punya tag 01 = "12" dan tag 54 = nominal. Mengubah
// statis → dinamis cukup dengan menyunting dua tag itu lalu menghitung ulang
// CRC, sehingga pembeli tidak perlu mengetik nominal di aplikasi e-wallet.
import jsQR from "jsqr";
import QRCode from "qrcode";

function crc16(text) {
    let crc = 0xffff;
    for (let i = 0; i < text.length; i++) {
        crc ^= text.charCodeAt(i) << 8;
        for (let b = 0; b < 8; b++) {
            crc = crc & 0x8000 ? (crc << 1) ^ 0x1021 : crc << 1;
            crc &= 0xffff;
        }
    }
    return crc.toString(16).toUpperCase().padStart(4, "0");
}

function parseTlv(text) {
    const tags = [];
    let i = 0;
    while (i < text.length) {
        const id = text.slice(i, i + 2);
        const len = Number(text.slice(i + 2, i + 4));
        if (id.length < 2 || !Number.isInteger(len)) return null;
        const value = text.slice(i + 4, i + 4 + len);
        if (value.length !== len) return null;
        tags.push({ id, value });
        i += 4 + len;
    }
    return tags;
}

function tlv(id, value) {
    return id + String(value.length).padStart(2, "0") + value;
}

/** QRIS sah: diawali "000201" dan CRC di tag 63 cocok. */
export function isValidQris(payload) {
    if (typeof payload !== "string" || !payload.startsWith("000201")) return false;
    const body = payload.slice(0, -4);
    if (!body.endsWith("6304")) return false;
    return crc16(body) === payload.slice(-4).toUpperCase() && !!parseTlv(payload);
}

/** Nama merchant (tag 59) untuk ditampilkan ke kasir. */
export function qrisMerchantName(payload) {
    return parseTlv(payload)?.find((t) => t.id === "59")?.value ?? "";
}

/** Sisipkan nominal (Rupiah bulat) ke QRIS; hasilnya QRIS dinamis. */
export function toDynamicQris(payload, amount) {
    const tags = parseTlv(payload);
    if (!tags) throw new Error("QRIS tidak valid");

    const rest = tags
        // 54 nominal, 55–57 tip, 63 CRC: dibuang lalu disusun ulang.
        .filter((t) => !["54", "55", "56", "57", "63"].includes(t.id))
        .map((t) => (t.id === "01" ? { id: "01", value: "12" } : t));
    rest.push({ id: "54", value: String(Math.round(amount)) });
    rest.sort((a, b) => Number(a.id) - Number(b.id));

    const body = rest.map((t) => tlv(t.id, t.value)).join("") + "6304";
    return body + crc16(body);
}

export function qrisToDataUrl(payload) {
    return QRCode.toDataURL(payload, {
        errorCorrectionLevel: "M",
        margin: 2,
        width: 480,
    });
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error("Gambar QRIS gagal dimuat"));
        img.src = src;
    });
}

// Foto QRIS sering besar dan kodenya kecil di tengah stiker; coba beberapa
// ukuran karena jsQR lebih andal pada resolusi sedang.
function scan(img) {
    for (const maxSide of [1000, 1600, 700, 2400]) {
        const scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        const w = Math.round(img.naturalWidth * scale);
        const h = Math.round(img.naturalHeight * scale);
        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d", { willReadFrequently: true });
        ctx.drawImage(img, 0, 0, w, h);
        const hit = jsQR(ctx.getImageData(0, 0, w, h).data, w, h, {
            inversionAttempts: "attemptBoth",
        });
        if (hit?.data) return hit.data;
    }
    return null;
}

const cache = new Map();

/**
 * Baca isi QRIS dari gambar (URL atau File). Hasil: teks QRIS yang sah,
 * atau null bila tidak ada kode QR / bukan QRIS.
 */
export function readQrisFromImage(source) {
    const key = typeof source === "string" ? source : null;
    if (key && cache.has(key)) return cache.get(key);

    const job = (async () => {
        const url = key ?? URL.createObjectURL(source);
        try {
            const data = scan(await loadImage(url));
            return data && isValidQris(data) ? data : null;
        } finally {
            if (!key) URL.revokeObjectURL(url);
        }
    })();

    if (key) {
        cache.set(key, job);
        job.catch(() => cache.delete(key));
    }
    return job;
}
