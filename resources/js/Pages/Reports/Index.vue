<script setup>
import { reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Icon from '@/Components/Icon.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { rupiah, tanggal } from '@/lib/format';

const props = defineProps({
    range: Object,
    summary: Object,
    breakdown: Object,
    daily: Array,
    topProducts: Array,
    recent: Array,
    piutangTotal: Number,
});

const filter = reactive({ from: props.range.from, to: props.range.to });
const loading = ref(false);

function apply() {
    router.get(route('reports.index'), { ...filter }, {
        preserveState: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

function preset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - days);
    filter.from = from.toISOString().slice(0, 10);
    filter.to = to.toISOString().slice(0, 10);
    apply();
}

const maxDaily = () => Math.max(1, ...props.daily.map((d) => Number(d.omzet)));
</script>

<template>
    <Head title="Laporan" />

    <AuthenticatedLayout>
        <PageHeader
            title="Laporan Penjualan & Keuangan"
            subtitle="Rekap omzet terpadu, laba kotor, produk terlaris, dan ekspor pembukuan"
        >
            <template #action>
                <a
                    :href="route('reports.export', { from: filter.from, to: filter.to })"
                    class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition-transform active:scale-95"
                    download
                >
                    <Icon name="download" :size="18" />
                    <span>Ekspor Excel / CSV</span>
                </a>
            </template>
        </PageHeader>

        <!-- Filter & Rentang Tanggal -->
        <div class="card-slate mt-6 flex flex-wrap items-center gap-3 p-4">
            <div
                class="flex items-stretch overflow-hidden rounded-control border border-line focus-within:border-brand"
            >
                <label
                    for="filter-from"
                    class="flex cursor-pointer items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft"
                    >Dari</label
                >
                <input
                    id="filter-from"
                    v-model="filter.from"
                    type="date"
                    class="num border-0 bg-transparent py-2.5 pl-0 pr-3 text-sm text-ink focus:outline-none focus:ring-0"
                />
                <span class="w-px self-stretch bg-line" aria-hidden="true"></span>
                <label
                    for="filter-to"
                    class="flex cursor-pointer items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft"
                    >Sampai</label
                >
                <input
                    id="filter-to"
                    v-model="filter.to"
                    type="date"
                    class="num border-0 bg-transparent py-2.5 pl-0 pr-3 text-sm text-ink focus:outline-none focus:ring-0"
                />
            </div>
            <button
                @click="apply"
                :disabled="loading"
                class="btn-primary disabled:opacity-60"
            >
                {{ loading ? "Memuat…" : "Terapkan" }}
            </button>

            <!-- Pintasan rentang -->
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <button
                    @click="preset(0)"
                    class="rounded-control border border-brand bg-brand-wash px-3 py-1.5 text-sm font-semibold text-brand transition-colors hover:bg-brand hover:text-white"
                >
                    Hari ini
                </button>
                <button
                    @click="preset(6)"
                    class="rounded-control border border-success bg-success-wash px-3 py-1.5 text-sm font-semibold text-success transition-colors hover:bg-success hover:text-white"
                >
                    7 hari
                </button>
                <button
                    @click="preset(29)"
                    class="rounded-control border border-amber bg-amber-wash px-3 py-1.5 text-sm font-semibold text-amber-ink transition-colors hover:bg-amber hover:text-amber-ink"
                >
                    30 hari
                </button>
            </div>
        </div>

        <!-- 4 Kartu Metrik Gabungan -->
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card-brand p-5">
                <div class="label">Total Omzet Gabungan</div>
                <div class="num mt-2 text-headline-md text-ink">{{ rupiah(summary.omzet) }}</div>
                <div class="mt-1 text-2xs text-ink-soft">Toko Eceran + Modul Arang</div>
            </div>
            <div class="card-success p-5">
                <div class="label">Total Laba Kotor</div>
                <div class="num mt-2 text-headline-md text-brand-ink">
                    {{ rupiah(summary.profit) }}
                </div>
                <div class="mt-1 text-2xs text-ink-soft">Estimasi keuntungan kotor</div>
            </div>
            <div class="card-slate p-5">
                <div class="label">Total Transaksi</div>
                <div class="num mt-2 text-headline-md text-ink">{{ summary.count }}</div>
                <div class="mt-1 text-2xs text-ink-soft">Semua nota terbit</div>
            </div>
            <div class="card-amber p-5">
                <div class="label">Total Diskon</div>
                <div class="num mt-2 text-headline-md text-ink">{{ rupiah(summary.discount) }}</div>
                <div class="mt-1 text-2xs text-ink-soft">Potongan harga promosi</div>
            </div>
        </div>

        <!-- Rincian Pembanding (Toko Eceran vs Arang Kiloan) -->
        <div v-if="breakdown" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <!-- Kartu Toko Eceran -->
            <div class="card-slate p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-control bg-surface text-brand">
                            <Icon name="kasir" :size="18" />
                        </span>
                        <div>
                            <h3 class="font-bold text-ink text-body-md">🛍️ Toko Eceran</h3>
                            <p class="text-2xs text-ink-soft">{{ breakdown.toko.count }} transaksi eceran</p>
                        </div>
                    </div>
                    <Link :href="route('sales.index')" class="link text-xs">
                        Lihat nota →
                    </Link>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-3">
                    <div>
                        <span class="text-ink-soft block text-2xs uppercase tracking-wider font-medium">Omzet Toko</span>
                        <span class="num font-semibold text-ink text-body-lg">{{ rupiah(breakdown.toko.omzet) }}</span>
                    </div>
                    <div>
                        <span class="text-ink-soft block text-2xs uppercase tracking-wider font-medium">Laba Kotor</span>
                        <span class="num font-semibold text-brand-ink text-body-lg">{{ rupiah(breakdown.toko.profit) }}</span>
                    </div>
                </div>
            </div>

            <!-- Kartu Modul Arang -->
            <div class="card-amber p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-control bg-surface text-amber">
                            <Icon name="fire" :size="18" />
                        </span>
                        <div>
                            <h3 class="font-bold text-ink text-body-md">🪵 Arang Kiloan</h3>
                            <p class="text-2xs text-ink-soft">{{ breakdown.arang.count }} transaksi · {{ breakdown.arang.berat_kg }} kg terjual</p>
                        </div>
                    </div>
                    <Link :href="route('arang.riwayat')" class="link text-xs">
                        Riwayat arang →
                    </Link>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3">
                    <div>
                        <span class="text-ink-soft block text-2xs uppercase tracking-wider font-medium">Omzet Jual</span>
                        <span class="num font-semibold text-ink text-body-lg">{{ rupiah(breakdown.arang.omzet) }}</span>
                    </div>
                    <div>
                        <span class="text-ink-soft block text-2xs uppercase tracking-wider font-medium">Laba Kotor</span>
                        <span class="num font-semibold text-brand-ink text-body-lg">{{ rupiah(breakdown.arang.profit) }}</span>
                    </div>
                    <div>
                        <span class="text-ink-soft block text-2xs uppercase tracking-wider font-medium">Beli Stok</span>
                        <span class="num font-medium text-ink-soft text-body-lg">{{ rupiah(breakdown.arang.beli_stok) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Piutang Toko + Arang -->
        <Link
            :href="route('piutang.index')"
            class="card-danger mt-4 flex items-center justify-between p-5 transition-colors hover:brightness-[0.97]"
        >
            <div class="flex items-center gap-3">
                <span
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-control bg-surface text-danger"
                >
                    <Icon name="wallet" :size="18" />
                </span>
                <div>
                    <div class="label">Total Piutang Belum Lunas</div>
                    <div class="num mt-0.5 text-headline-sm text-ink">
                        {{ rupiah(piutangTotal) }}
                    </div>
                </div>
            </div>
            <span class="link inline-flex items-center gap-1 text-body-md">
                Buku piutang kasbon <Icon name="arrow-right" :size="15" />
            </span>
        </Link>

        <!-- Grafik & Produk Terlaris -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="card-brand p-5">
                <h2 class="text-headline-sm text-ink">Omzet harian terpadu</h2>
                <div class="mt-4 space-y-2">
                    <div
                        v-for="d in daily"
                        :key="d.d"
                        class="flex items-center gap-3 text-xs"
                    >
                        <span class="num w-20 text-ink-faint">{{ d.d }}</span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface">
                            <div
                                class="h-full rounded-full bg-brand"
                                :style="{ width: (Number(d.omzet) / maxDaily()) * 100 + '%' }"
                            />
                        </div>
                        <span class="num w-24 text-right text-ink">{{ rupiah(d.omzet) }}</span>
                    </div>
                    <p v-if="!daily.length" class="py-6 text-center text-sm text-ink-faint">
                        Tidak ada data.
                    </p>
                </div>
            </div>

            <div class="card-success overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-headline-sm text-ink">Produk & Varian Terlaris</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Item</th>
                                <th class="th text-right">Volume</th>
                                <th class="th text-right">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in topProducts"
                                :key="t.name"
                                class="row-hover border-b border-line last:border-0"
                            >
                                <td class="td text-ink">
                                    <span class="font-medium">{{ t.name }}</span>
                                </td>
                                <td class="td num text-right font-semibold text-ink">
                                    {{ t.qty }} <span class="text-2xs font-normal text-ink-faint">{{ t.unit || 'pcs' }}</span>
                                </td>
                                <td class="td num text-right text-ink-soft">{{ rupiah(t.omzet) }}</td>
                            </tr>
                            <tr v-if="!topProducts.length">
                                <td colspan="3" class="td py-8 text-center text-ink-faint">
                                    Tidak ada data.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transaksi Terakhir Terpadu -->
        <div class="card-slate mt-6 overflow-hidden">
            <div
                class="flex items-center justify-between border-b border-line px-5 py-4"
            >
                <div class="flex items-center gap-2">
                    <h2 class="text-headline-sm text-ink">Transaksi terakhir (Toko & Arang)</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('sales.index')"
                        class="link inline-flex items-center gap-1 text-body-md"
                    >
                        Riwayat Toko <Icon name="arrow-right" :size="15" />
                    </Link>
                    <span class="text-ink-faint">·</span>
                    <Link
                        :href="route('arang.riwayat')"
                        class="link inline-flex items-center gap-1 text-body-md text-amber-ink"
                    >
                        Riwayat Arang <Icon name="arrow-right" :size="15" />
                    </Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">No. Nota</th>
                            <th class="th">Jenis</th>
                            <th class="th">Waktu</th>
                            <th class="th">Kasir</th>
                            <th class="th text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in recent"
                            :key="s.invoice_no"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td">
                                <a :href="s.receipt_url" target="_blank" class="link num font-semibold">
                                    {{ s.invoice_no }}
                                </a>
                            </td>
                            <td class="td">
                                <span
                                    class="inline-flex items-center rounded px-2 py-0.5 text-2xs font-semibold uppercase"
                                    :class="s.type === 'arang' ? 'bg-amber-wash text-amber-ink' : 'bg-brand-wash text-brand'"
                                >
                                    {{ s.type_label }}
                                </span>
                            </td>
                            <td class="td num text-ink-soft">{{ tanggal(s.created_at) }}</td>
                            <td class="td text-ink-soft">{{ s.user_name }}</td>
                            <td class="td num text-right font-semibold text-ink">
                                {{ rupiah(s.total) }}
                            </td>
                        </tr>
                        <tr v-if="!recent.length">
                            <td colspan="5" class="td py-8 text-center text-ink-faint">
                                Tidak ada transaksi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
