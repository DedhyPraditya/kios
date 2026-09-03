<script setup>
// Lambang toko: pita struk di atas kotak hijau — bentuk yang sama dengan
// favicon.svg dan ikon layar utama, supaya satu identitas di semua tempat.
defineProps({
    size: { type: [Number, String], default: 36 },
    // "solid" = kotak hijau isi putih (dipakai di latar terang),
    // "inverse" = kotak putih isi hijau (dipakai di latar hijau/foto).
    variant: { type: String, default: "solid" },
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 64 64"
        role="img"
        aria-label="Lambang toko"
        class="shrink-0"
    >
        <rect
            width="64"
            height="64"
            rx="14"
            :fill="variant === 'inverse' ? '#ffffff' : '#1E6B4F'"
        />
        <path
            d="M20 14h24v30l-4 4-4-4-4 4-4-4-4 4z"
            :fill="variant === 'inverse' ? '#1E6B4F' : '#ffffff'"
        />
        <path
            d="M26 24h12M26 32h12M26 39h7"
            :stroke="variant === 'inverse' ? '#ffffff' : '#1E6B4F'"
            stroke-width="3"
            stroke-linecap="round"
        />
    </svg>
</template>
