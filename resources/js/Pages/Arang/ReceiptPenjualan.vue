<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import { rupiah, tanggal } from "@/lib/format";
import { strukPenjualanArangEscPos } from "@/lib/struk-escpos";
import { cetakKeBluetooth, didukung } from "@/lib/printer-bluetooth";

const props = defineProps({
    penjualan: { type: Object, required: true },
    store: { type: Object, default: () => ({}) },
});

const isKasbon = props.penjualan.payment_type === "kasbon";
const isQris = props.penjualan.payment_type === "qris";
const lunas = props.penjualan.status === "lunas";

const adaBluetooth = didukung();
const sibuk = ref(false);
const kabar = ref("");

async function cetakBluetooth() {
    sibuk.value = true;
    kabar.value = "Menyambung ke printer Bluetooth…";

    try {
        await cetakKeBluetooth(strukPenjualanArangEscPos(props.penjualan, props.store));
        kabar.value = "Struk berhasil terkirim ke printer.";
    } catch (e) {
        kabar.value =
            e?.name === "NotFoundError"
                ? "Tidak ada printer yang dipilih."
                : `Gagal mencetak: ${e?.message ?? e}`;
    } finally {
        sibuk.value = false;
    }
}

function tinggiCetakMm(el) {
    const salinan = el.cloneNode(true);
    salinan.style.cssText =
        "position:absolute;left:-9999px;top:0;width:58mm;padding:0 5mm;" +
        "font-size:9pt;line-height:1.35;background:none;";
    document.body.appendChild(salinan);
    const mm = (salinan.getBoundingClientRect().height / 96) * 25.4;
    salinan.remove();

    return Math.ceil(mm) + 10;
}

function cetak() {
    const struk = document.getElementById("struk-penjualan");

    if (struk) {
        const gaya =
            document.getElementById("ukuran-struk") ??
            document.head.appendChild(
                Object.assign(document.createElement("style"), {
                    id: "ukuran-struk",
                }),
            );

        gaya.textContent = `@page { size: 58mm ${tinggiCetakMm(struk)}mm; margin: 0 }`;
    }

    window.print();
}
</script>

<template>
    <Head title="Struk Penjualan Arang" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-sm">
            <!-- Status Sukses Bar -->
            <div class="mb-4 flex items-center justify-between no-print">
                <div class="flex items-center gap-2 text-sm text-brand-ink">
                    <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-wash">✓</span>
                    <span class="font-medium">Penjualan Berhasil Dicatat</span>
                </div>
                <Link
                    :href="route('arang.index')"
                    class="text-xs text-brand hover:underline font-medium"
                >
                    &larr; Arang
                </Link>
            </div>

            <!-- Kertas Struk 58mm -->
            <div id="struk-penjualan" class="card tape px-6 pb-6 text-sm bg-white text-ink">
                <!-- Header Toko -->
                <div class="text-center">
                    <div class="text-base font-bold tracking-tight">
                        {{ store.store_name || "Kios BERKAH" }}
                    </div>
                    <div v-if="store.store_address" class="text-xs text-ink-soft">
                        {{ store.store_address }}
                    </div>
                    <div v-if="store.store_phone" class="num text-xs text-ink-soft">
                        {{ store.store_phone }}
                    </div>
                    <div class="mt-2 text-2xs uppercase tracking-widest font-bold border-y border-line py-1 text-ink">
                        STRUK PENJUALAN ARANG
                    </div>
                </div>

                <!-- Info Nota -->
                <div class="tape-rule mt-3 space-y-1 pt-3 text-xs text-ink-soft">
                    <div class="flex justify-between">
                        <span>No. Nota</span>
                        <span class="num font-semibold text-ink">{{ penjualan.no_nota }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Waktu</span>
                        <span class="num">{{ tanggal(penjualan.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kasir</span>
                        <span class="text-ink">{{ penjualan.user?.name || "-" }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pembeli</span>
                        <span class="font-medium text-ink">
                            {{ penjualan.customer?.name ?? (penjualan.nama_pembeli || "Pelanggan Umum") }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Metode</span>
                        <span class="font-semibold uppercase text-ink">
                            {{ isKasbon ? "Kasbon" : isQris ? "QRIS" : "Tunai" }}
                        </span>
                    </div>
                </div>

                <!-- Tabel Item Arang -->
                <div class="tape-rule mt-3 pt-3 text-xs">
                    <div class="flex justify-between font-bold text-ink">
                        <span>{{ penjualan.arang_jenis?.nama || "Arang Kiloan" }}</span>
                        <span class="num">{{ rupiah(penjualan.total_harga) }}</span>
                    </div>
                    <div class="text-2xs text-ink-soft mt-0.5">
                        {{ penjualan.berat_kg }} kg × {{ rupiah(penjualan.harga_jual_per_kg) }}
                    </div>
                </div>

                <!-- Kalkulasi -->
                <div class="tape-rule mt-3 space-y-1 pt-3 text-xs">
                    <div class="flex justify-between text-ink-soft">
                        <span>Subtotal</span>
                        <span class="num">{{ rupiah(penjualan.total_harga) }}</span>
                    </div>
                    <div v-if="penjualan.diskon > 0" class="flex justify-between text-ink-soft">
                        <span>Diskon</span>
                        <span class="num text-danger">−{{ rupiah(penjualan.diskon) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-ink pt-1 border-t border-line/60">
                        <span>Total Tagihan</span>
                        <span class="num text-brand-ink">{{ rupiah(penjualan.grand_total) }}</span>
                    </div>

                    <!-- Detail Pembayaran Tunai -->
                    <template v-if="!isKasbon && !isQris">
                        <div class="flex justify-between text-ink-soft pt-1">
                            <span>Bayar Tunai</span>
                            <span class="num">{{ rupiah(penjualan.paid) }}</span>
                        </div>
                        <div class="flex justify-between text-xs font-semibold text-ink">
                            <span>Kembalian</span>
                            <span class="num text-success">{{ rupiah(penjualan.change) }}</span>
                        </div>
                    </template>

                    <!-- Detail QRIS -->
                    <template v-else-if="isQris">
                        <div class="flex justify-between text-xs text-ink-soft pt-1">
                            <span>Status</span>
                            <span class="font-bold text-brand-ink">LUNAS (QRIS)</span>
                        </div>
                    </template>

                    <!-- Detail Kasbon -->
                    <template v-else-if="isKasbon">
                        <div class="flex justify-between text-ink-soft pt-1">
                            <span>DP Diterima</span>
                            <span class="num">{{ rupiah(penjualan.paid) }}</span>
                        </div>
                        <div
                            class="flex justify-between text-xs font-bold"
                            :class="lunas ? 'text-success' : 'text-danger'"
                        >
                            <span>Sisa Kasbon</span>
                            <span class="num">
                                {{ rupiah(Math.max(0, penjualan.grand_total - penjualan.paid)) }}
                            </span>
                        </div>
                    </template>
                </div>

                <!-- Catatan jika ada -->
                <div v-if="penjualan.catatan" class="tape-rule mt-3 pt-2 text-2xs text-ink-soft italic">
                    Catatan: {{ penjualan.catatan }}
                </div>

                <!-- Footer Struk -->
                <div class="tape-rule mt-4 pt-3 text-center text-2xs text-ink-soft">
                    <p>Terima kasih atas pembelian Anda!</p>
                    <p class="mt-0.5">Barang yang sudah dibeli telah ditimbang dengan teliti.</p>
                </div>
            </div>

            <!-- Tombol Aksi di Layar -->
            <div class="mt-4 flex flex-col gap-2 no-print">
                <button
                    v-if="adaBluetooth"
                    type="button"
                    class="btn-primary w-full py-2.5 flex items-center justify-center gap-2 font-semibold"
                    :disabled="sibuk"
                    @click="cetakBluetooth"
                >
                    <Icon name="print" :size="18" />
                    <span>{{ sibuk ? "Mencetak…" : "Cetak ke printer Bluetooth" }}</span>
                </button>

                <p
                    v-if="kabar"
                    class="text-center text-xs text-ink-soft"
                >
                    {{ kabar }}
                </p>

                <button
                    type="button"
                    class="w-full py-2 flex items-center justify-center gap-2 text-xs font-medium"
                    :class="adaBluetooth ? 'btn-secondary' : 'btn-primary'"
                    @click="cetak"
                >
                    <Icon name="print" :size="16" />
                    <span>{{ adaBluetooth ? "Cetak biasa (Dialog printer)" : "Cetak Struk (58mm)" }}</span>
                </button>

                <div class="flex gap-2 mt-1">
                    <Link
                        :href="route('arang.jual.create')"
                        class="btn-secondary flex-1 text-center py-2 text-xs font-medium"
                    >
                        + Jual Lagi
                    </Link>
                    <Link
                        :href="route('arang.index')"
                        class="btn-secondary flex-1 text-center py-2 text-xs font-medium"
                    >
                        Arang
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    :global(body) {
        background: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    :global(nav),
    :global(header),
    :global(aside),
    .no-print {
        display: none !important;
    }
    #struk-penjualan {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 4mm !important;
        width: 100% !important;
        max-width: 58mm !important;
    }
}
</style>
