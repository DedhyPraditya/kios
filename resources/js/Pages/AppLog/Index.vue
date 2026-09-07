<script setup>
import { reactive, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, router } from "@inertiajs/vue3";

const props = defineProps({
    logs: Array,
    filters: Object,
    system: Object,
});

const q = reactive({
    search: props.filters.search || "",
});

let timer = null;
function applyFilter() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("app-logs.index"),
            {
                search: q.search || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 250);
}

watch(q, applyFilter);

function resetSearch() {
    q.search = "";
    applyFilter();
}

function getBadgeColor(type) {
    switch (type) {
        case "feat":
            return "bg-brand-wash text-brand-ink border-brand/30";
        case "ui":
            return "bg-blue-50 text-blue-800 border-blue-200";
        case "sec":
        case "system":
            return "bg-purple-50 text-purple-800 border-purple-200";
        case "fix":
            return "bg-amber-50 text-amber-900 border-amber-200";
        case "docs":
            return "bg-slate-100 text-slate-800 border-slate-200";
        default:
            return "bg-paper text-ink-soft border-line";
    }
}
</script>

<template>
    <Head title="Log Aplikasi & Pembaruan" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                title="Log Aplikasi & Pembaruan"
                subtitle="Catatan riwayat rilis, fitur baru, dan penyempurnaan sistem Kios BERKAH."
            />

            <!-- Search & Filter Bar -->
            <div class="card p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="relative min-w-64 flex-1">
                        <span class="sr-only">Cari catatan pembaruan</span>
                        <Icon
                            name="search"
                            :size="16"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                        />
                        <input
                            v-model="q.search"
                            type="search"
                            placeholder="Cari fitur, perbaikan, atau versi pembaruan..."
                            class="field w-full py-2.5 pl-9 pr-3 text-sm"
                        />
                    </label>

                    <button
                        v-if="q.search"
                        type="button"
                        class="btn-ghost py-2.5 px-3 text-xs text-danger hover:text-danger"
                        @click="resetSearch"
                    >
                        Bersihkan
                    </button>
                </div>
            </div>

            <!-- Changelog Timeline -->
            <div class="space-y-6">
                <div
                    v-for="(release, index) in logs"
                    :key="release.version"
                    class="card overflow-hidden transition-shadow hover:shadow-md"
                >
                    <!-- Release Header -->
                    <div
                        class="border-b border-line bg-paper/60 px-4 py-3.5 sm:px-6 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="inline-flex items-center rounded-control px-2.5 py-1 text-xs font-bold num tracking-wide"
                                :class="
                                    index === 0
                                        ? 'bg-brand text-white'
                                        : 'bg-line text-ink'
                                "
                            >
                                v{{ release.version }}
                            </span>
                            <span
                                v-if="index === 0"
                                class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-brand-wash text-brand-ink border border-brand/30"
                            >
                                Versi Terbaru
                            </span>
                            <h3 class="text-body-lg font-bold text-ink">
                                {{ release.title }}
                            </h3>
                        </div>

                        <div
                            class="flex items-center gap-2 text-xs text-ink-soft"
                        >
                            <Icon
                                name="clock"
                                :size="14"
                                class="text-ink-faint"
                            />
                            <span class="num">{{ release.date_human }}</span>
                            <span class="text-ink-faint">•</span>
                            <span>{{ release.author }}</span>
                        </div>
                    </div>

                    <!-- Release Body -->
                    <div class="p-4 sm:p-6 space-y-4">
                        <p class="text-body-md text-ink-soft leading-relaxed">
                            {{ release.description }}
                        </p>

                        <!-- Items List -->
                        <div
                            class="divide-y divide-line rounded-control border border-line bg-surface overflow-hidden"
                        >
                            <div
                                v-for="(change, ci) in release.changes"
                                :key="ci"
                                class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-start gap-3 row-hover transition-colors"
                            >
                                <div class="shrink-0 pt-0.5">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold border"
                                        :class="getBadgeColor(change.type)"
                                    >
                                        {{ change.category }}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-bold text-ink">
                                        {{ change.title }}
                                    </h4>
                                    <p
                                        class="text-xs text-ink-soft mt-0.5 leading-relaxed"
                                    >
                                        {{ change.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="logs.length === 0"
                    class="card py-12 px-4 text-center text-ink-soft"
                >
                    <div
                        class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-paper text-ink-faint"
                    >
                        <Icon name="history" :size="24" />
                    </div>
                    <p class="text-body-md font-semibold text-ink">
                        Tidak Ada Catatan yang Cocok
                    </p>
                    <p class="text-xs text-ink-soft mt-1">
                        Tidak ditemukan pembaruan dengan kata kunci "{{
                            q.search
                        }}".
                    </p>
                    <button
                        type="button"
                        class="btn-outline mt-4 px-3 py-1.5 text-xs"
                        @click="resetSearch"
                    >
                        Tampilkan Semua Pembaruan
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
