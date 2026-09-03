<script setup>
import { computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { rupiah, tanggal } from "@/lib/format";

const props = defineProps({
    customer: Object,
    unpaidSales: Array,
    payments: Array,
});

const form = useForm({
    customer_id: props.customer.id,
    sale_id: null,
    amount: null,
    note: "",
});

function payAll() {
    form.sale_id = null;
    form.amount = props.customer.outstanding;
}

function submit() {
    form.post(route("credit-payments.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset("amount", "note", "sale_id"),
    });
}

const overdueCount = computed(
    () => props.unpaidSales.filter((s) => s.overdue).length,
);
</script>

<template>
    <Head :title="customer.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('customers.index')" class="link text-body-md"
                    >&larr; Pelanggan</Link
                >
                <h1 class="text-headline-md text-ink">{{ customer.name }}</h1>
                <span
                    v-if="customer.is_blocked"
                    class="rounded-full bg-danger-wash px-2 py-0.5 text-2xs font-semibold uppercase text-danger"
                    >diblokir</span
                >
            </div>
        </template>

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <!-- Ringkasan + form bayar -->
            <div class="space-y-6">
                <div class="card-danger p-5">
                    <div class="label">Sisa hutang</div>
                    <div
                        class="num mt-1 text-display-lg"
                        :class="
                            customer.outstanding > 0
                                ? 'text-danger'
                                : 'text-ink'
                        "
                    >
                        {{ rupiah(customer.outstanding) }}
                    </div>
                    <dl class="mt-4 space-y-1.5 text-body-md">
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Telepon</dt>
                            <dd class="num">{{ customer.phone ?? "—" }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Batas kredit</dt>
                            <dd class="num">
                                {{
                                    customer.credit_limit === null
                                        ? "tanpa batas"
                                        : rupiah(customer.credit_limit)
                                }}
                            </dd>
                        </div>
                        <div v-if="customer.address" class="flex justify-between gap-4">
                            <dt class="text-ink-soft">Alamat</dt>
                            <dd class="text-right">{{ customer.address }}</dd>
                        </div>
                        <div v-if="overdueCount" class="flex justify-between">
                            <dt class="text-ink-soft">Jatuh tempo lewat</dt>
                            <dd class="font-semibold text-danger">
                                {{ overdueCount }} nota
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="customer.outstanding > 0" class="card-success p-5">
                    <h2 class="text-headline-sm text-ink">Terima pembayaran</h2>
                    <p class="mt-1 text-2xs text-ink-faint">
                        Dibagi otomatis ke nota terlama lebih dulu.
                    </p>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label for="pay-jumlah" class="label">Jumlah</label>
                            <input id="pay-jumlah"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                class="field num mt-1 py-2 text-sm"
                            />
                            <button
                                type="button"
                                class="mt-1 text-2xs text-brand hover:underline"
                                @click="payAll"
                            >
                                Bayar semua ({{ rupiah(customer.outstanding) }})
                            </button>
                            <p
                                v-if="form.errors.amount"
                                class="mt-1 text-xs text-danger"
                            >
                                {{ form.errors.amount }}
                            </p>
                        </div>
                        <div>
                            <label for="pay-catatan" class="label">Catatan</label>
                            <input id="pay-catatan"
                                v-model="form.note"
                                class="field mt-1 py-2 text-sm"
                            />
                        </div>
                        <button
                            @click="submit"
                            :disabled="form.processing || !form.amount"
                            class="btn-primary w-full"
                        >
                            <Icon name="check" :size="17" /> Catat pembayaran
                        </button>
                    </div>
                </div>
            </div>

            <!-- Nota belum lunas + riwayat -->
            <div class="space-y-6 lg:col-span-2">
                <div class="card-amber overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-headline-sm text-ink">
                            Nota belum lunas
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-body-md">
                            <thead>
                                <tr>
                                    <th class="th">Nota</th>
                                    <th class="th">Tanggal</th>
                                    <th class="th">Jatuh tempo</th>
                                    <th class="th text-right">Total</th>
                                    <th class="th text-right">Sisa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in unpaidSales"
                                    :key="s.id"
                                    class="row-hover border-b border-line last:border-0"
                                >
                                    <td class="td">
                                        <Link
                                            :href="route('pos.receipt', s.id)"
                                            class="link num"
                                            >{{ s.invoice_no }}</Link
                                        >
                                    </td>
                                    <td class="td num text-ink-soft">
                                        {{ tanggal(s.created_at) }}
                                    </td>
                                    <td class="td num">
                                        <span
                                            v-if="s.due_date"
                                            :class="
                                                s.overdue
                                                    ? 'font-semibold text-danger'
                                                    : 'text-ink-soft'
                                            "
                                            >{{ s.due_date }}</span
                                        >
                                        <span v-else class="text-ink-faint"
                                            >—</span
                                        >
                                    </td>
                                    <td class="td num text-right text-ink-soft">
                                        {{ rupiah(s.total) }}
                                    </td>
                                    <td
                                        class="td num text-right font-semibold text-danger"
                                    >
                                        {{ rupiah(s.outstanding) }}
                                    </td>
                                </tr>
                                <tr v-if="!unpaidSales.length">
                                    <td
                                        colspan="5"
                                        class="td py-8 text-center text-ink-faint"
                                    >
                                        Tidak ada hutang.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-slate overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-headline-sm text-ink">
                            Riwayat pembayaran
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-body-md">
                            <thead>
                                <tr>
                                    <th class="th">Tanggal</th>
                                    <th class="th">Nota</th>
                                    <th class="th">Kasir</th>
                                    <th class="th">Catatan</th>
                                    <th class="th text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in payments"
                                    :key="p.id"
                                    class="row-hover border-b border-line last:border-0"
                                >
                                    <td class="td num text-ink-soft">
                                        {{ tanggal(p.created_at) }}
                                    </td>
                                    <td class="td num text-ink-soft">
                                        {{ p.sale?.invoice_no ?? "—" }}
                                    </td>
                                    <td class="td text-ink-soft">
                                        {{ p.user?.name }}
                                    </td>
                                    <td class="td text-ink-soft">
                                        {{ p.note ?? "—" }}
                                    </td>
                                    <td
                                        class="td num text-right font-semibold text-success"
                                    >
                                        {{ rupiah(p.amount) }}
                                    </td>
                                </tr>
                                <tr v-if="!payments.length">
                                    <td
                                        colspan="5"
                                        class="td py-8 text-center text-ink-faint"
                                    >
                                        Belum ada pembayaran.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
