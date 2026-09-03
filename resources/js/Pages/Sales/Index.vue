<script setup>
import { reactive, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    sales: Object,
    filters: Object,
    cashiers: Array,
    totals: Object,
});

const q = reactive({
    q: props.filters.q ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
    user_id: props.filters.user_id ?? "",
    status: props.filters.status ?? "semua",
});

let timer = null;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("sales.index"),
            {
                q: q.q || undefined,
                from: q.from || undefined,
                to: q.to || undefined,
                user_id: q.user_id || undefined,
                status: q.status !== "semua" ? q.status : undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

function reset() {
    q.q = "";
    q.from = "";
    q.to = "";
    q.user_id = "";
    q.status = "semua";
}

const badge = {
    batal: "bg-danger-wash text-danger",
    belum_lunas: "bg-amber-wash text-amber-ink",
    lunas: "bg-success-wash text-success",
};
const badgeLabel = {
    batal: "Batal",
    belum_lunas: "Belum lunas",
    lunas: "Lunas",
};
</script>

<template>
    <Head title="Riwayat transaksi" />

    <AuthenticatedLayout>
        <PageHeader
            title="Riwayat transaksi"
            subtitle="Semua nota yang pernah tersimpan"
        />

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card-brand p-5">
                <div class="label">Nota (hasil saring)</div>
                <div class="num mt-2 text-headline-md text-ink">
                    {{ totals.trx }}
                </div>
            </div>
            <div class="card-success p-5">
                <div class="label">Nilai bersih</div>
                <div class="num mt-2 text-headline-md text-brand-ink">
                    {{ rupiah(totals.omzet) }}
                </div>
            </div>
            <div class="card-amber p-5">
                <div class="label">Nilai retur</div>
                <div class="num mt-2 text-headline-md text-amber-ink">
                    {{ rupiah(totals.refunded) }}
                </div>
            </div>
            <div class="card-danger p-5">
                <div class="label">Nota batal</div>
                <div class="num mt-2 text-headline-md text-danger">
                    {{ totals.voided }}
                </div>
            </div>
        </div>

        <!-- Penyaring -->
        <div class="card-slate mt-4 flex flex-wrap items-center gap-3 p-3">
            <label class="relative min-w-56 flex-1">
                <span class="sr-only">Cari nota</span>
                <Icon
                    name="search"
                    :size="16"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                />
                <input
                    v-model="q.q"
                    type="search"
                    placeholder="No nota, pelanggan, catatan…"
                    class="field py-2.5 pl-9 pr-3 text-sm"
                />
            </label>
            <div
                class="flex w-full items-stretch overflow-hidden rounded-control border border-line bg-surface focus-within:border-brand sm:w-auto"
            >
                <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                    Dari
                </span>
                <input
                    v-model="q.from"
                    type="date"
                    aria-label="Dari tanggal"
                    class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                />
                <span class="w-px self-stretch bg-line" aria-hidden="true"></span>
                <span class="flex shrink-0 items-center pl-3 pr-2 text-label-caps uppercase text-ink-soft">
                    Sampai
                </span>
                <input
                    v-model="q.to"
                    type="date"
                    aria-label="Sampai tanggal"
                    class="num w-full min-w-0 border-0 bg-transparent py-2.5 pl-0 pr-2 text-sm text-ink focus:outline-none focus:ring-0 sm:w-auto"
                />
            </div>
            <select
                v-model="q.user_id"
                aria-label="Kasir"
                class="field w-40 py-2.5 text-sm"
            >
                <option value="">Semua kasir</option>
                <option v-for="c in cashiers" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>
            <select
                v-model="q.status"
                aria-label="Status"
                class="field w-40 py-2.5 text-sm"
            >
                <option value="semua">Semua status</option>
                <option value="tunai">Tunai</option>
                <option value="kasbon">Kasbon</option>
                <option value="lunas">Lunas</option>
                <option value="belum_lunas">Belum lunas</option>
                <option value="batal">Batal</option>
            </select>
            <button class="btn-ghost" @click="reset">Bersihkan</button>
        </div>

        <div class="card-slate mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">No nota</th>
                            <th class="th">Waktu</th>
                            <th class="th">Kasir</th>
                            <th class="th">Pelanggan</th>
                            <th class="th text-right">Item</th>
                            <th class="th text-right">Total</th>
                            <th class="th">Status</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="s in sales.data"
                            :key="s.id"
                            class="row-hover"
                        >
                            <td class="td num font-medium text-ink">
                                {{ s.invoice_no }}
                            </td>
                            <td class="td num text-ink-soft">
                                {{ s.created_at }}
                            </td>
                            <td class="td text-ink-soft">{{ s.user }}</td>
                            <td class="td text-ink-soft">
                                {{ s.customer ?? "—" }}
                            </td>
                            <td class="td num text-right text-ink-soft">
                                {{ s.items_count }}
                            </td>
                            <td class="td num text-right">
                                <span
                                    :class="
                                        s.status === 'batal'
                                            ? 'text-ink-faint line-through'
                                            : 'font-medium text-ink'
                                    "
                                >
                                    {{ rupiah(s.net_total) }}
                                </span>
                                <span
                                    v-if="s.refunded > 0"
                                    class="block text-2xs text-danger"
                                >
                                    retur {{ rupiah(s.refunded) }}
                                </span>
                            </td>
                            <td class="td">
                                <span
                                    class="rounded-full px-2 py-0.5 text-2xs font-semibold uppercase"
                                    :class="badge[s.status]"
                                >
                                    {{ badgeLabel[s.status] }}
                                </span>
                                <span
                                    v-if="s.payment_type === 'kasbon'"
                                    class="ms-1 text-2xs uppercase text-ink-faint"
                                >
                                    kasbon
                                </span>
                            </td>
                            <td class="td text-right">
                                <Link
                                    :href="route('sales.show', s.id)"
                                    class="link inline-flex items-center gap-1"
                                >
                                    Detail
                                    <Icon name="arrow-right" :size="14" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="td py-10 text-center text-ink-faint">
                                Tidak ada transaksi yang cocok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :paginator="sales" noun="nota" />
        </div>
    </AuthenticatedLayout>
</template>
