<script setup>
import { computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    jenisList: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    recentTransactions: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === "admin");
</script>

<template>
    <Head title="Arang" />

    <AuthenticatedLayout>
        <PageHeader
            title="Arang"
            subtitle="Pusat pengelolaan pembelian arang kiloan dari pembuat dan penjualan ke pelanggan."
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="isAdmin"
                        :href="route('arang.jenis.index')"
                        class="btn-secondary flex items-center gap-1.5"
                    >
                        <Icon name="gear" :size="16" />
                        <span>Kelola Jenis</span>
                    </Link>
                    <Link
                        :href="route('arang.riwayat')"
                        class="btn-secondary flex items-center gap-1.5"
                    >
                        <Icon name="riwayat" :size="16" />
                        <span>Riwayat</span>
                    </Link>
                    <Link
                        :href="route('arang.beli.create')"
                        class="btn-secondary flex items-center gap-1.5 bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100"
                    >
                        <Icon name="plus" :size="16" />
                        <span>Beli Arang</span>
                    </Link>
                    <Link
                        :href="route('arang.jual.create')"
                        class="btn-primary flex items-center gap-1.5"
                    >
                        <Icon name="fire" :size="16" />
                        <span>Jual Arang</span>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Ringkasan Bulan Ini -->
        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card-brand p-5">
                <div class="flex items-center justify-between">
                    <span class="label">Total Stok Arang</span>
                    <Icon name="fire" :size="20" class="text-brand" />
                </div>
                <div class="num mt-2 text-headline-md text-brand-ink">
                    {{ summary.total_stok_kg }} <span class="text-sm font-normal text-ink-soft">kg</span>
                </div>
                <div class="mt-1 text-2xs text-ink-soft">
                    Dari {{ jenisList.length }} varian arang aktif
                </div>
            </div>

            <div class="card-amber p-5">
                <div class="flex items-center justify-between">
                    <span class="label">Pembelian Bulan Ini</span>
                    <Icon name="stok" :size="20" class="text-amber-ink" />
                </div>
                <div class="num mt-2 text-headline-md text-amber-ink">
                    {{ summary.beli_kg }} <span class="text-sm font-normal text-ink-soft">kg</span>
                </div>
                <div class="mt-1 text-2xs text-ink-soft">
                    {{ rupiah(summary.beli_rp) }}
                </div>
            </div>

            <div class="card-success p-5">
                <div class="flex items-center justify-between">
                    <span class="label">Penjualan Bulan Ini</span>
                    <Icon name="kasir" :size="20" class="text-success" />
                </div>
                <div class="num mt-2 text-headline-md text-success">
                    {{ summary.jual_kg }} <span class="text-sm font-normal text-ink-soft">kg</span>
                </div>
                <div class="mt-1 text-2xs text-ink-soft">
                    {{ rupiah(summary.jual_rp) }}
                </div>
            </div>

            <div class="card-slate p-5">
                <div class="flex items-center justify-between">
                    <span class="label">Margin / Selisih Kas</span>
                    <Icon name="wallet" :size="20" class="text-ink-soft" />
                </div>
                <div
                    class="num mt-2 text-headline-md"
                    :class="summary.margin_rp >= 0 ? 'text-success' : 'text-danger'"
                >
                    {{ rupiah(summary.margin_rp) }}
                </div>
                <div class="mt-1 text-2xs text-ink-soft">
                    Penjualan dikurangi pembelian
                </div>
            </div>
        </div>

        <!-- Stok per Jenis Arang -->
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-headline-sm text-ink">Stok & Harga per Jenis Arang</h2>
                <Link
                    v-if="isAdmin"
                    :href="route('arang.jenis.index')"
                    class="text-body-sm text-brand hover:underline"
                >
                    Atur jenis arang &rarr;
                </Link>
            </div>

            <div v-if="jenisList.length === 0" class="card-slate p-6 text-center text-ink-soft">
                Belum ada data jenis arang. Silakan tambahkan jenis arang terlebih dahulu.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="item in jenisList"
                    :key="item.id"
                    class="card-slate p-5 flex flex-col justify-between"
                    :class="{ 'border-amber-400 bg-amber-50/40': item.stok_kg <= 10 }"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-headline-sm text-ink font-semibold">
                                    {{ item.nama }}
                                </h3>
                                <p v-if="item.catatan" class="mt-0.5 text-2xs text-ink-soft">
                                    {{ item.catatan }}
                                </p>
                            </div>
                            <span
                                v-if="item.stok_kg <= 10"
                                class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-2xs font-medium text-amber-800"
                            >
                                Stok Menipis
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-2xs font-medium text-emerald-800"
                            >
                                Aman
                            </span>
                        </div>

                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="num text-2xl font-bold text-ink">
                                {{ item.stok_kg }}
                            </span>
                            <span class="text-body-md text-ink-soft">kg tersedia</span>
                        </div>

                        <div class="mt-4 border-t border-line pt-3 space-y-1 text-body-sm">
                            <div class="flex justify-between">
                                <span class="text-ink-soft">Standar Beli:</span>
                                <span class="num text-ink">{{ rupiah(item.harga_beli_default) }} / kg</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-soft">Standar Jual:</span>
                                <span class="num font-semibold text-brand-ink">{{ rupiah(item.harga_jual_default) }} / kg</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2 border-t border-line/50 flex items-center gap-2">
                        <Link
                            :href="route('arang.jual.create', { jenis_id: item.id })"
                            class="btn-primary flex-1 text-center py-1.5 text-xs"
                            :class="{ 'opacity-50 pointer-events-none': item.stok_kg <= 0 }"
                        >
                            Jual Ini
                        </Link>
                        <Link
                            :href="route('arang.beli.create', { jenis_id: item.id })"
                            class="btn-secondary flex-1 text-center py-1.5 text-xs"
                        >
                            Beli Arang
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- 10 Transaksi Terakhir -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-headline-sm text-ink">10 Aktivitas Transaksi Terakhir</h2>
                <Link
                    :href="route('arang.riwayat')"
                    class="text-body-sm text-brand hover:underline"
                >
                    Lihat semua riwayat &rarr;
                </Link>
            </div>

            <div class="card-slate overflow-hidden">
                <div v-if="recentTransactions.length === 0" class="p-8 text-center text-ink-soft">
                    Belum ada riwayat transaksi pembelian atau penjualan arang.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-body-md">
                        <thead>
                            <tr class="border-b border-line bg-surface-soft">
                                <th class="th">Waktu / Nota</th>
                                <th class="th">Tipe</th>
                                <th class="th">Jenis Arang</th>
                                <th class="th">Pemasok / Pembeli</th>
                                <th class="th text-right">Berat</th>
                                <th class="th text-right">Harga/kg</th>
                                <th class="th text-right">Total</th>
                                <th class="th">Metode / Status</th>
                                <th class="th">Kasir</th>
                                <th class="th text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="trx in recentTransactions"
                                :key="trx.id"
                                class="hover:bg-surface-hover/50"
                            >
                                <td class="td">
                                    <div class="font-medium text-ink">{{ trx.tanggal }}</div>
                                    <div v-if="trx.no_nota && trx.no_nota !== '-'" class="num text-2xs text-ink-soft">
                                        {{ trx.no_nota }}
                                    </div>
                                </td>
                                <td class="td">
                                    <span
                                        v-if="trx.type === 'beli'"
                                        class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-2xs font-semibold text-amber-800"
                                    >
                                        Beli (Masuk)
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-2xs font-semibold text-emerald-800"
                                    >
                                        Jual (Keluar)
                                    </span>
                                </td>
                                <td class="td font-medium text-ink">
                                    {{ trx.jenis }}
                                </td>
                                <td class="td text-ink-soft">
                                    {{ trx.pihak }}
                                </td>
                                <td class="td text-right num font-semibold">
                                    {{ trx.berat_kg }} kg
                                </td>
                                <td class="td text-right num text-ink-soft">
                                    {{ rupiah(trx.harga_per_kg) }}
                                </td>
                                <td
                                    class="td text-right num font-bold"
                                    :class="trx.type === 'beli' ? 'text-amber-ink' : 'text-brand-ink'"
                                >
                                    {{ rupiah(trx.total) }}
                                </td>
                                <td class="td">
                                    <span class="capitalize text-xs font-medium text-ink">
                                        {{ trx.payment_type }}
                                    </span>
                                    <span
                                        v-if="trx.status === 'belum_lunas'"
                                        class="ml-1.5 inline-flex items-center rounded bg-danger/10 px-1.5 py-0.5 text-2xs font-medium text-danger"
                                    >
                                        Belum Lunas
                                    </span>
                                </td>
                                <td class="td text-xs text-ink-soft">
                                    {{ trx.user }}
                                </td>
                                <td class="td text-center">
                                    <Link
                                        :href="trx.type === 'jual' ? route('arang.penjualan.receipt', trx.raw_id) : route('arang.pembelian.receipt', trx.raw_id)"
                                        class="btn-secondary py-1 px-2 text-2xs inline-flex items-center gap-1"
                                        title="Cetak Struk / Nota"
                                    >
                                        <Icon name="print" :size="13" />
                                        <span>Struk</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
