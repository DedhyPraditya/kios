<script setup>
// Kode nomor nota di bawah struk (QR atau barcode CODE128), supaya nota bisa
// dicari cepat dengan scanner atau kamera di Riwayat Transaksi / Riwayat Arang.
import { ref, watch } from "vue";
import QRCode from "qrcode";
import JsBarcode from "jsbarcode";

const props = defineProps({
    value: { type: String, required: true },
    type: { type: String, default: "none" }, // none | qr | barcode
});

const qrUrl = ref("");
const svg = ref(null);

watch(
    () => [props.value, props.type, svg.value],
    async ([value, type]) => {
        if (type === "qr") {
            qrUrl.value = await QRCode.toDataURL(value, { margin: 1, width: 200, errorCorrectionLevel: "M" });
        } else if (type === "barcode" && svg.value) {
            JsBarcode(svg.value, value, {
                format: "CODE128",
                width: 1.4,
                height: 44,
                margin: 0,
                displayValue: false,
            });
        }
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="type === 'qr' || type === 'barcode'" class="nota-code mt-3 flex flex-col items-center">
        <img v-if="type === 'qr' && qrUrl" :src="qrUrl" :alt="`QR nota ${value}`" class="nota-code-qr h-24 w-24" />
        <svg v-else-if="type === 'barcode'" ref="svg" class="nota-code-bar h-11 max-w-full" role="img" :aria-label="`Barcode nota ${value}`" />
        <span class="num mt-1 text-2xs text-ink-faint">{{ value }}</span>
    </div>
</template>
