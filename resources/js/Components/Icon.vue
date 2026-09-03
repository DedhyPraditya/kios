<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
});

// Each icon = list of SVG child elements [tag, attrs]. Rendered as real
// nodes (no v-html) so the SVG namespace is always correct.
const icons = {
    kasir: [
        ['path', { d: 'M4 7h16l-1.2 11.2A2 2 0 0 1 16.8 20H7.2a2 2 0 0 1-2-1.8L4 7Z' }],
        ['path', { d: 'M9 7V5a3 3 0 0 1 6 0v2' }],
    ],
    dashboard: [
        ['rect', { x: 3, y: 3, width: 7, height: 9, rx: 1.5 }],
        ['rect', { x: 14, y: 3, width: 7, height: 5, rx: 1.5 }],
        ['rect', { x: 14, y: 12, width: 7, height: 9, rx: 1.5 }],
        ['rect', { x: 3, y: 16, width: 7, height: 5, rx: 1.5 }],
    ],
    produk: [
        ['path', { d: 'M12 3 3 7.5v9L12 21l9-4.5v-9L12 3Z' }],
        ['path', { d: 'M3 7.5 12 12l9-4.5' }],
        ['path', { d: 'M12 12v9' }],
    ],
    kategori: [
        ['path', { d: 'M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9-9-9Z' }],
        ['circle', { cx: 8, cy: 8, r: 1.6 }],
    ],
    laporan: [
        ['path', { d: 'M4 20h16' }],
        ['rect', { x: 6, y: 11, width: 3, height: 6, rx: 1 }],
        ['rect', { x: 14, y: 7, width: 3, height: 10, rx: 1 }],
    ],
    pengguna: [
        ['circle', { cx: 9, cy: 8, r: 3.2 }],
        ['path', { d: 'M3.5 19a5.5 5.5 0 0 1 11 0' }],
        ['path', { d: 'M16 6.2a3 3 0 0 1 0 5.6' }],
        ['path', { d: 'M18 19a5 5 0 0 0-3-4.6' }],
    ],
    profil: [
        ['circle', { cx: 12, cy: 8, r: 3.5 }],
        ['path', { d: 'M5 20a7 7 0 0 1 14 0' }],
    ],
    keluar: [
        ['path', { d: 'M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4' }],
        ['path', { d: 'M10 12h10' }],
        ['path', { d: 'm16 8 4 4-4 4' }],
    ],
    search: [
        ['circle', { cx: 11, cy: 11, r: 6.5 }],
        ['path', { d: 'm20 20-3.5-3.5' }],
    ],
    print: [
        ['path', { d: 'M7 9V3h10v6' }],
        ['rect', { x: 4, y: 9, width: 16, height: 8, rx: 1.5 }],
        ['path', { d: 'M7 17h10v4H7z' }],
    ],
    plus: [['path', { d: 'M12 5v14M5 12h14' }]],
    riwayat: [
        ['path', { d: 'M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z' }],
        ['path', { d: 'M8 9h8M8 13h8M8 17h5' }],
    ],
    stok: [
        ['path', { d: 'M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6' }],
        ['path', { d: 'M12 3v10' }],
        ['path', { d: 'm8 9 4 4 4-4' }],
    ],
    shift: [
        ['rect', { x: 3, y: 9, width: 18, height: 11, rx: 2 }],
        ['path', { d: 'M6 9V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3' }],
        ['path', { d: 'M10 14h4' }],
    ],
    menu: [['path', { d: 'M4 7h16M4 12h16M4 17h16' }]],
    chevron: [['path', { d: 'm7 14 5-5 5 5' }]],
    'arrow-right': [['path', { d: 'M5 12h14M13 6l6 6-6 6' }]],
    bell: [
        ['path', { d: 'M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6' }],
        ['path', { d: 'M10.5 20a2 2 0 0 0 3 0' }],
    ],
    gear: [
        ['circle', { cx: 12, cy: 12, r: 3 }],
        [
            'path',
            {
                d: 'M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z',
            },
        ],
    ],
    cloud: [
        [
            'path',
            {
                d: 'M17.5 19a4.5 4.5 0 0 0 .5-9 6 6 0 0 0-11.6-1.5A4 4 0 0 0 7 19h10.5Z',
            },
        ],
    ],
    clock: [
        ['circle', { cx: 12, cy: 12, r: 8.5 }],
        ['path', { d: 'M12 7.5V12l3 2' }],
    ],
    customer: [
        ['circle', { cx: 12, cy: 8, r: 3.5 }],
        ['path', { d: 'M5.5 20a6.5 6.5 0 0 1 13 0' }],
    ],
    wallet: [
        ['path', { d: 'M4 7a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v2' }],
        ['rect', { x: 3, y: 7, width: 18, height: 12, rx: 2 }],
        ['circle', { cx: 16, cy: 13, r: 1.4 }],
    ],
    check: [['path', { d: 'M5 12.5 10 17 19 7' }]],
};

const parts = computed(() => icons[props.name] || []);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <component
            :is="tag"
            v-for="([tag, attrs], i) in parts"
            :key="i"
            v-bind="attrs"
        />
    </svg>
</template>
