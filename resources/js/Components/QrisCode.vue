<script setup>
// Tampilkan QRIS toko. Bila QR di gambar terbaca, tampilkan QRIS dinamis
// yang sudah berisi nominal; bila tidak, tampilkan gambar aslinya.
import { ref, watch } from "vue";
import { qrisToDataUrl, readQrisFromImage, toDynamicQris } from "@/lib/qris";

const props = defineProps({
    imageUrl: { type: String, required: true },
    amount: { type: Number, default: 0 },
});

const state = ref("loading"); // loading | dynamic | static
const dataUrl = ref("");
const failed = ref(false);

let run = 0;
watch(
    () => [props.imageUrl, props.amount],
    async ([url, amount]) => {
        const id = ++run;
        failed.value = false;
        try {
            const payload = await readQrisFromImage(url);
            if (id !== run) return;
            if (!payload || !(amount > 0)) {
                state.value = "static";
                return;
            }
            dataUrl.value = await qrisToDataUrl(toDynamicQris(payload, amount));
            if (id === run) state.value = "dynamic";
        } catch {
            if (id === run) state.value = "static";
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <div
            v-if="state === 'loading'"
            class="grid aspect-square w-full place-items-center text-2xs text-ink-faint"
        >
            Menyiapkan QRIS…
        </div>
        <img
            v-else-if="state === 'dynamic'"
            :src="dataUrl"
            alt="QRIS dengan nominal"
            class="aspect-square w-full [image-rendering:pixelated]"
        />
        <template v-else>
            <img
                v-if="!failed"
                :src="imageUrl"
                alt="QRIS Toko"
                class="w-full object-contain"
                @error="failed = true"
            />
            <p v-else class="py-6 text-center text-2xs text-danger">
                Gambar QRIS tidak bisa dimuat. Unggah ulang di menu Pengaturan.
            </p>
        </template>

        <span
            v-if="state === 'dynamic'"
            class="rounded-full bg-brand-wash px-2 py-0.5 text-2xs font-semibold text-brand-ink"
        >
            Nominal sudah terisi otomatis
        </span>
        <span
            v-else-if="state === 'static' && !failed"
            class="text-center text-2xs text-amber-ink"
        >
            QRIS statis — pembeli perlu mengetik nominal sendiri.
        </span>
    </div>
</template>
