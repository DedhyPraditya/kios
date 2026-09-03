<script setup>
import { computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    stats: Object,
    lowStockList: Array,
    salesTrend: { type: Array, default: () => [] },
    system: { type: Object, default: () => ({}) },
});

const maxOmzet = computed(() =>
    Math.max(1, ...props.salesTrend.map((d) => d.omzet)),
);
const hasActivity = computed(() =>
    props.salesTrend.some((d) => d.omzet > 0 || d.trx > 0),
);
const trendTotal = computed(() =>
    props.salesTrend.reduce((s, d) => s + d.omzet, 0),
);

const metrics = computed(() => [
    {
        label: "Omzet hari ini",
        value: rupiah(props.stats.today_omzet),
        icon: "kasir",
        tone: "card-brand",
    },
    {
        label: "Transaksi hari ini",
        value: props.stats.today_trx,
        icon: "laporan",
        tone: "card-success",
    },
    {
        label: "Jumlah produk",
        value: props.stats.products,
        icon: "produk",
        tone: "card-slate",
    },
    {
        label: "Stok menipis",
        value: props.stats.low_stock,
        icon: "kategori",
        tone: "card-amber",
        alert: props.stats.low_stock > 0,
    },
]);

const sku = (id) => "PRD-" + String(id).padStart(3, "0");
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <PageHeader title="Dashboard" subtitle="Ringkasan toko hari ini">
            <template #action>
                <Link :href="route('pos.index')" class="btn-primary rounded-xl">
                    <Icon name="kasir" :size="18" /> Buka kasir
                </Link>
            </template>
        </PageHeader>

        <!-- Metrics -->
        <div class="mt-6 grid grid-cols-2 gap-5 lg:grid-cols-4">
            <div
                v-for="m in metrics"
                :key="m.label"
                class="flex flex-col p-6"
                :class="m.alert ? 'card-danger' : m.tone"
            >
                <div class="flex items-start justify-between">
                    <span class="label" :class="m.alert ? 'text-danger' : ''">{{
                        m.label
                    }}</span>
                    <Icon
                        :name="m.alert ? 'kategori' : m.icon"
                        :size="18"
                        :class="m.alert ? 'text-danger' : 'text-ink-faint'"
                    />
                </div>
                <div
                    class="num mt-6 text-headline-md md:text-display-lg"
                    :class="m.alert ? 'text-danger' : 'text-ink'"
                >
                    {{ m.value }}
                </div>
            </div>
        </div>

        <!-- Perlu restok -->
        <div class="card-amber mt-6 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="flex items-center gap-2 text-headline-sm text-ink">
                    <Icon name="kasir" :size="18" class="text-ink-soft" />
                    Perlu restok
                </h2>
                <Link
                    :href="route('products.index', { low: 1 })"
                    class="link inline-flex items-center gap-1 text-body-md"
                >
                    Lihat semua <Icon name="arrow-right" :size="15" />
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Produk</th>
                            <th class="th text-right">Stok</th>
                            <th class="th text-right">Ambang</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in lowStockList"
                            :key="p.id"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-control bg-surface text-ink-faint"
                                    >
                                        <Icon name="produk" :size="18" />
                                    </span>
                                    <span>
                                        <span
                                            class="block font-medium text-ink"
                                            >{{ p.name }}</span
                                        >
                                        <span
                                            class="num block text-2xs text-ink-faint"
                                            >ID: {{ sku(p.id) }}</span
                                        >
                                    </span>
                                </div>
                            </td>
                            <td
                                class="td num text-right font-semibold text-danger"
                            >
                                {{ p.stock }}
                            </td>
                            <td class="td num text-right text-ink-faint">
                                {{ p.low_stock }}
                            </td>
                        </tr>
                        <tr v-if="!lowStockList.length">
                            <td
                                colspan="3"
                                class="td py-8 text-center text-ink-faint"
                            >
                                Semua stok aman.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Aktivitas Toko + Informasi Sistem -->
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card-brand relative overflow-hidden p-5 lg:col-span-2">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-headline-sm text-ink">Aktivitas Toko</h2>
                    <span
                        class="text-2xs uppercase tracking-wide text-ink-faint"
                        >7 hari terakhir</span
                    >
                </div>

                <div
                    v-if="!hasActivity"
                    class="relative flex flex-col items-center gap-3 px-4 py-12 text-center"
                >
                    <span
                        class="grid h-14 w-14 place-items-center rounded-card bg-surface text-ink-faint"
                    >
                        <Icon name="laporan" :size="24" />
                    </span>
                    <p class="max-w-xs text-body-md text-ink-soft">
                        Belum ada aktivitas penjualan hari ini. Mulai transaksi
                        untuk melihat grafik harian.
                    </p>
                    <svg
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-20 w-full text-brand/10"
                        viewBox="0 0 400 80"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M0 60 C 60 20 120 20 180 45 S 300 80 400 35 L400 80 L0 80 Z"
                            fill="currentColor"
                        />
                    </svg>
                </div>

                <div v-else class="mt-6">
                    <div class="flex items-end gap-2 sm:gap-4">
                        <div
                            v-for="d in salesTrend"
                            :key="d.date"
                            class="group flex flex-1 flex-col items-center gap-2"
                        >
                            <span
                                class="num text-2xs text-ink-faint opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                {{ rupiah(d.omzet) }}
                            </span>
                            <div
                                class="w-full rounded-t transition-colors"
                                :class="d.omzet > 0 ? 'bg-brand' : 'bg-line'"
                                :style="{
                                    height:
                                        (d.omzet > 0
                                            ? 20 + (d.omzet / maxOmzet) * 100
                                            : 6) + 'px',
                                }"
                            />
                            <span class="text-2xs font-medium text-ink-faint">{{
                                d.label
                            }}</span>
                        </div>
                    </div>
                    <div
                        class="mt-4 flex items-center justify-between border-t border-line pt-3 text-body-md"
                    >
                        <span class="text-ink-soft">Total 7 hari</span>
                        <span class="num font-semibold text-ink">{{
                            rupiah(trendTotal)
                        }}</span>
                    </div>
                </div>
            </div>

            <div class="card-slate p-5">
                <h2 class="text-headline-sm text-ink">Informasi Sistem</h2>
                <div class="mt-4 space-y-2">
                    <div
                        class="flex items-center gap-3 rounded-control bg-surface px-3 py-3"
                    >
                        <Icon name="cloud" :size="18" class="text-success" />
                        <span class="flex-1 text-body-md text-ink-soft"
                            >Basis data</span
                        >
                        <span
                            class="rounded-full bg-success-wash px-2 py-0.5 text-2xs font-semibold uppercase tracking-wide text-success"
                        >
                            Terhubung
                        </span>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-control bg-surface px-3 py-3"
                    >
                        <Icon name="clock" :size="18" class="text-ink-faint" />
                        <span class="flex-1 text-body-md text-ink-soft"
                            >Waktu server</span
                        >
                        <span class="num text-body-md text-ink">{{
                            system.serverTime
                        }}</span>
                    </div>
                    <div
                        v-if="system.lastSaleAt"
                        class="flex items-center gap-3 rounded-control bg-surface px-3 py-3"
                    >
                        <Icon
                            name="laporan"
                            :size="18"
                            class="text-ink-faint"
                        />
                        <span class="flex-1 text-body-md text-ink-soft"
                            >Transaksi terakhir</span
                        >
                        <span class="num text-body-md text-ink">{{
                            system.lastSaleAt
                        }}</span>
                    </div>
                </div>
                <Link
                    :href="
                        $page.props.auth.isAdmin
                            ? route('settings.edit')
                            : route('profile.edit')
                    "
                    class="btn-outline mt-4 w-full"
                >
                    <Icon name="gear" :size="17" />
                    {{
                        $page.props.auth.isAdmin
                            ? "Pengaturan toko"
                            : "Pengaturan akun"
                    }}
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
