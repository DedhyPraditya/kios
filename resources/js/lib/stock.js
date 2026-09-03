/**
 * Tingkat stok — satu-satunya sumber aturan warna stok di seluruh aplikasi
 * (halaman Produk, kartu jualan di Kasir, dan mana pun yang menyusul).
 *
 *   low  : sudah di ambang menipis atau kurang  -> merah
 *   mid  : masih di bawah dua kali ambang       -> amber
 *   ok   : aman                                  -> hijau
 */
export function stockLevel(stock, lowStock = 0) {
    const sisa = Number(stock) || 0;
    const ambang = Number(lowStock) || 0;

    if (sisa <= ambang) return "low";
    if (sisa <= ambang * 2) return "mid";

    return "ok";
}

/** Isi + teks lencana bulat (dipakai StockBadge). */
export const stockTone = {
    low: "bg-danger-wash text-danger",
    mid: "bg-amber-wash text-amber-ink",
    ok: "bg-success-wash text-success",
};

/** Titik warna kecil di dalam lencana. */
export const stockDot = {
    low: "bg-danger",
    mid: "bg-amber",
    ok: "bg-success",
};
