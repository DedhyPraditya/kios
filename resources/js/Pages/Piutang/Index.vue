<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { Head, Link } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

defineProps({
    total: Number,
    buckets: Object,
    customers: Array,
    sales: Array,
});

const bucketLabel = {
    "0-7": "0–7 hari",
    "8-30": "8–30 hari",
    "31+": "> 30 hari",
};

// Makin tua umur hutang, makin “panas” warna kartunya.
const bucketTone = {
    "0-7": "card-success",
    "8-30": "card-amber",
    "31+": "card-danger",
};
</script>

<template>
    <Head title="Piutang" />

    <AuthenticatedLayout>
        <PageHeader
            title="Piutang (Kasbon)"
            subtitle="Sisa hutang pelanggan dan umur piutang"
        />

        <div class="mt-6 grid grid-cols-2 gap-5 lg:grid-cols-4">
            <div class="card-danger p-5">
                <div class="label">Total piutang</div>
                <div class="num mt-2 text-headline-md text-danger md:text-display-lg">
                    {{ rupiah(total) }}
                </div>
            </div>
            <div
                v-for="(amt, key) in buckets"
                :key="key"
                :class="bucketTone[key]"
                class="p-5"
            >
                <div class="label">Umur {{ bucketLabel[key] }}</div>
                <div class="num mt-2 text-headline-md text-ink md:text-display-lg">
                    {{ rupiah(amt) }}
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="card-slate overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-headline-sm text-ink">Per pelanggan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Pelanggan</th>
                                <th class="th">Hutang tertua</th>
                                <th class="th text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in customers"
                                :key="c.id"
                                class="row-hover border-b border-line last:border-0"
                            >
                                <td class="td font-medium text-ink">
                                    <Link
                                        :href="route('customers.show', c.id)"
                                        class="link"
                                        >{{ c.name }}</Link
                                    >
                                    <span
                                        v-if="c.phone"
                                        class="num ms-1 text-2xs text-ink-faint"
                                        >{{ c.phone }}</span
                                    >
                                </td>
                                <td class="td num text-ink-soft">
                                    {{ c.oldest }}
                                </td>
                                <td
                                    class="td num text-right font-semibold text-danger"
                                >
                                    {{ rupiah(c.outstanding) }}
                                </td>
                            </tr>
                            <tr v-if="!customers.length">
                                <td
                                    colspan="3"
                                    class="td py-8 text-center text-ink-faint"
                                >
                                    Tidak ada piutang.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-amber overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-headline-sm text-ink">Nota belum lunas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Nota</th>
                                <th class="th">Pelanggan</th>
                                <th class="th text-right">Umur</th>
                                <th class="th text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in sales"
                                :key="s.id"
                                class="row-hover border-b border-line last:border-0"
                            >
                                <td class="td">
                                    <Link
                                        :href="route('pos.receipt', s.id)"
                                        class="link num"
                                        >{{ s.invoice_no }}</Link
                                    >
                                    <span
                                        v-if="s.overdue"
                                        class="ms-1 rounded bg-danger-wash px-1 text-2xs font-semibold uppercase text-danger"
                                        >tempo</span
                                    >
                                </td>
                                <td class="td text-ink-soft">{{ s.customer }}</td>
                                <td class="td num text-right text-ink-soft">
                                    {{ s.age_days }}h
                                </td>
                                <td
                                    class="td num text-right font-semibold text-danger"
                                >
                                    {{ rupiah(s.outstanding) }}
                                </td>
                            </tr>
                            <tr v-if="!sales.length">
                                <td
                                    colspan="4"
                                    class="td py-8 text-center text-ink-faint"
                                >
                                    Tidak ada nota belum lunas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
