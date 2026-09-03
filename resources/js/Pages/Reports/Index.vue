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
            title="Laporan Penjualan"
            subtitle="Rekap omzet, laba, produk terlaris, dan transaksi"
        />

        <div class="card-slate mt-6 flex flex-wrap items-center gap-3 p-4">
            <!-- Dari — Sampai menyatu; label sebaris di dalam kotak. -->
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

            <!-- Pintasan rentang: sejajar filter, didorong rata kanan. -->
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

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card-brand p-5">
                <div class="label">Omzet</div>
                <div class="num mt-2 text-headline-md text-ink">{{ rupiah(summary.omzet) }}</div>
            </div>
            <div class="card-success p-5">
                <div class="label">Laba kotor</div>
                <div class="num mt-2 text-headline-md text-brand-ink">
                    {{ rupiah(summary.profit) }}
                </div>
            </div>
            <div class="card-slate p-5">
                <div class="label">Transaksi</div>
                <div class="num mt-2 text-headline-md text-ink">{{ summary.count }}</div>
            </div>
            <div class="card-amber p-5">
                <div class="label">Total diskon</div>
                <div class="num mt-2 text-headline-md text-ink">{{ rupiah(summary.discount) }}</div>
            </div>
        </div>

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
                    <div class="label">Piutang saat ini</div>
                    <div class="num mt-0.5 text-headline-sm text-ink">
                        {{ rupiah(piutangTotal) }}
                    </div>
                </div>
            </div>
            <span class="link inline-flex items-center gap-1 text-body-md">
                Lihat piutang <Icon name="arrow-right" :size="15" />
            </span>
        </Link>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="card-brand p-5">
                <h2 class="text-headline-sm text-ink">Omzet harian</h2>
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
                    <h2 class="text-headline-sm text-ink">Produk terlaris</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Produk</th>
                                <th class="th text-right">Qty</th>
                                <th class="th text-right">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in topProducts"
                                :key="t.name"
                                class="row-hover border-b border-line last:border-0"
                            >
                                <td class="td text-ink">{{ t.name }}</td>
                                <td class="td num text-right font-semibold text-ink">{{ t.qty }}</td>
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

        <div class="card-slate mt-6 overflow-hidden">
            <div
                class="flex items-center justify-between border-b border-line px-5 py-4"
            >
                <h2 class="text-headline-sm text-ink">Transaksi terakhir</h2>
                <Link
                    :href="route('sales.index')"
                    class="link inline-flex items-center gap-1 text-body-md"
                >
                    Riwayat lengkap <Icon name="arrow-right" :size="15" />
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">No</th>
                            <th class="th">Waktu</th>
                            <th class="th">Kasir</th>
                            <th class="th text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in recent"
                            :key="s.id"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td">
                                <Link :href="route('pos.receipt', s.id)" class="link num">
                                    {{ s.invoice_no }}
                                </Link>
                            </td>
                            <td class="td num text-ink-soft">{{ tanggal(s.created_at) }}</td>
                            <td class="td text-ink-soft">{{ s.user?.name }}</td>
                            <td class="td num text-right font-semibold text-ink">
                                {{ rupiah(s.total) }}
                            </td>
                        </tr>
                        <tr v-if="!recent.length">
                            <td colspan="4" class="td py-8 text-center text-ink-faint">
                                Tidak ada transaksi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
