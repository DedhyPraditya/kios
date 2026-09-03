<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    // Laravel length-aware paginator (array form): data, links, from, to, total
    paginator: { type: Object, required: true },
    noun: { type: String, default: "data" },
});
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4 text-body-md text-ink-soft"
    >
        <p>
            Menampilkan
            <span class="num font-medium text-ink">{{ paginator.from ?? 0 }}</span
            >–<span class="num font-medium text-ink">{{ paginator.to ?? 0 }}</span>
            dari
            <span class="num font-medium text-ink">{{ paginator.total }}</span>
            {{ noun }}
        </p>

        <div v-if="paginator.links.length > 3" class="flex items-center gap-1">
            <template v-for="(link, i) in paginator.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    preserve-scroll
                    class="num grid h-8 min-w-8 place-items-center rounded-control px-2 text-sm transition-colors"
                    :class="
                        link.active
                            ? 'bg-brand text-white'
                            : 'text-ink-soft hover:bg-paper hover:text-ink'
                    "
                />
                <span
                    v-else
                    v-html="link.label"
                    class="num grid h-8 min-w-8 place-items-center px-2 text-sm text-ink-faint"
                />
            </template>
        </div>
    </div>
</template>
