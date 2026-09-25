<script setup>
// Baris status di atas setiap struk/nota, sama untuk kasir dan arang.
import { Link } from "@inertiajs/vue3";

defineProps({
    pesan: { type: String, required: true },
    kembaliHref: { type: String, default: null },
    kembaliLabel: { type: String, default: "Kembali" },
});
</script>

<template>
    <div class="mb-4 flex items-center justify-between gap-2 print:hidden">
        <div class="flex items-center gap-2 text-sm font-medium text-brand-ink">
            <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-wash">✓</span>
            {{ pesan }}
        </div>
        <Link v-if="kembaliHref" :href="kembaliHref" class="text-xs font-medium text-brand hover:underline">
            &larr; {{ kembaliLabel }}
        </Link>
    </div>
</template>
