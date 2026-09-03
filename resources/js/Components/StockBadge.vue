<script setup>
import { computed } from "vue";
import { stockDot, stockLevel, stockTone } from "@/lib/stock";

const props = defineProps({
    stock: { type: Number, required: true },
    lowStock: { type: Number, default: 0 },
    // "md" = tabel Produk, "sm" = kartu jualan di Kasir.
    size: { type: String, default: "md" },
    // Teks di depan angka, mis. "Stok".
    label: { type: String, default: "" },
});

const level = computed(() => stockLevel(props.stock, props.lowStock));
</script>

<template>
    <span
        class="num inline-flex items-center gap-1.5 rounded-full font-semibold"
        :class="[
            stockTone[level],
            size === 'sm' ? 'px-2 py-0.5 text-2xs' : 'px-2.5 py-1 text-sm',
        ]"
    >
        <span
            class="rounded-full"
            :class="[stockDot[level], size === 'sm' ? 'h-1 w-1' : 'h-1.5 w-1.5']"
        />
        <span v-if="label" class="font-sans uppercase tracking-wide">{{
            label
        }}</span>
        {{ stock }}
    </span>
</template>
