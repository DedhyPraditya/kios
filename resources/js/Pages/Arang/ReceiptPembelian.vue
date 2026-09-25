<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { rupiah, tanggal } from "@/lib/format";
import { notaPembelianArangEscPos } from "@/lib/struk-escpos";
import StrukAksi from "@/Components/StrukAksi.vue";
import StrukStatus from "@/Components/StrukStatus.vue";

const props = defineProps({
    pembelian: { type: Object, required: true },
    store: { type: Object, default: () => ({}) },
});


const buatEscPos = () => notaPembelianArangEscPos(props.pembelian, props.store);

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
    const struk = document.getElementById("struk-pembelian");

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
    <Head title="Nota Pembelian Arang" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-sm">
            <StrukStatus pesan="Pembelian arang tersimpan" :kembali-href="route('arang.index')" kembali-label="Arang" />

            <!-- Kertas Nota 58mm -->
            <div id="struk-pembelian" class="card tape px-6 pb-6 text-sm bg-white text-ink">
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
                        NOTA PEMBELIAN ARANG
                    </div>
                </div>

                <!-- Info Pembelian -->
                <div class="tape-rule mt-3 space-y-1 pt-3 text-xs text-ink-soft">
                    <div class="flex justify-between">
                        <span>No. Nota</span>
                        <span class="num font-semibold text-ink">{{ pembelian.no_nota }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Waktu</span>
                        <span class="num">{{ tanggal(pembelian.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Pembuat/Pemasok</span>
                        <span class="font-semibold text-ink">{{ pembelian.nama_pemasok }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Penerima</span>
                        <span class="text-ink">{{ pembelian.user?.name || "-" }}</span>
                    </div>
                </div>

                <!-- Detail Timbangan Arang -->
                <div class="tape-rule mt-3 pt-3 text-xs">
                    <div class="flex justify-between font-bold text-ink">
                        <span>{{ pembelian.arang_jenis?.nama || "Arang Kiloan" }}</span>
                        <span class="num">{{ rupiah(pembelian.total_harga) }}</span>
                    </div>
                    <div class="text-2xs text-ink-soft mt-0.5">
                        Timbangan: <strong>{{ pembelian.berat_kg }} kg</strong> × {{ rupiah(pembelian.harga_beli_per_kg) }}
                    </div>
                </div>

                <!-- Total Dibayarkan -->
                <div class="tape-rule mt-3 space-y-1 pt-3 text-xs">
                    <div class="flex justify-between text-sm font-bold text-ink pt-1 border-t border-line/60">
                        <span>Dibayarkan Tunai</span>
                        <span class="num text-amber-900">{{ rupiah(pembelian.total_harga) }}</span>
                    </div>
                    <div class="flex justify-between text-2xs text-ink-soft">
                        <span>Status</span>
                        <span class="font-semibold text-success">LUNAS DI TEMPAT</span>
                    </div>
                </div>

                <!-- Catatan -->
                <div v-if="pembelian.catatan" class="tape-rule mt-3 pt-2 text-2xs text-ink-soft italic">
                    Catatan: {{ pembelian.catatan }}
                </div>

                <!-- Kolom Tanda Tangan Ringkas -->
                <div class="tape-rule mt-5 pt-3 text-2xs text-ink-soft">
                    <div class="grid grid-cols-2 text-center">
                        <div>
                            <p>Pembuat/Pemasok,</p>
                            <div class="h-8"></div>
                            <p class="font-medium text-ink">({{ pembelian.nama_pemasok }})</p>
                        </div>
                        <div>
                            <p>Petugas Toko,</p>
                            <div class="h-8"></div>
                            <p class="font-medium text-ink">({{ pembelian.user?.name || "Kasir" }})</p>
                        </div>
                    </div>
                </div>
            </div>

            <StrukAksi
                :buat="buatEscPos"
                berhasil="Nota pembelian berhasil terkirim ke printer."
                :baru-href="route('arang.beli.create')"
                baru-label="Beli arang lagi"
                @cetak="cetak"
            />
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
    #struk-pembelian {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 4mm !important;
        width: 100% !important;
        max-width: 58mm !important;
    }
}
</style>
