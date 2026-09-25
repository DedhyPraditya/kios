<script setup>
// Tombol di bawah setiap struk/nota (kasir, jual arang, beli arang), supaya
// susunan dan ukurannya seragam:
//   1. cetak langsung ESC/POS (Bluetooth / USB-COM) bila perambannya mendukung;
//   2. [Cetak] lewat kotak cetak peramban  |  [transaksi berikutnya].
import { Link } from "@inertiajs/vue3";
import Icon from "@/Components/Icon.vue";
import CetakLangsung from "@/Components/CetakLangsung.vue";

defineProps({
    // Pembuat byte ESC/POS untuk cetak langsung.
    buat: { type: Function, required: true },
    berhasil: { type: String, default: "Struk terkirim ke printer." },
    // Tujuan & label tombol utama, mis. "Transaksi baru".
    baruHref: { type: String, required: true },
    baruLabel: { type: String, required: true },
});

defineEmits(["cetak"]);
</script>

<template>
    <div class="mt-4 space-y-2 print:hidden">
        <CetakLangsung :buat="buat" :berhasil="berhasil" />
        <div class="flex gap-2">
            <button type="button" class="btn-ghost flex-1" @click="$emit('cetak')">
                <Icon name="print" :size="18" /> Cetak
            </button>
            <Link :href="baruHref" class="btn-primary flex-1">
                {{ baruLabel }}
            </Link>
        </div>
    </div>
</template>
