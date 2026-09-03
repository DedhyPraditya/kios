<script setup>
import { computed, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    current: Object,
    history: Object,
    autoClosed: { type: Object, default: null },
});

// --- Buka shift ---
const openForm = useForm({ opening_cash: 0, note: "" });

function openShift() {
    openForm.post(route("shift.store"), { preserveScroll: true });
}

// --- Kas masuk / keluar ---
const showCash = ref(false);
const cashForm = useForm({ direction: "keluar", amount: 0, note: "" });

function submitCash() {
    cashForm.post(route("shift.movement"), {
        preserveScroll: true,
        onSuccess: () => {
            showCash.value = false;
            cashForm.reset();
        },
    });
}

// --- Tutup shift ---
const showClose = ref(false);
const closeForm = useForm({ counted_cash: 0, deposit: null, note: "" });

const selisih = computed(
    () => (Number(closeForm.counted_cash) || 0) - (props.current?.expected_cash ?? 0),
);

function submitClose() {
    closeForm.post(route("shift.close", props.current.id), {
        preserveScroll: true,
        onSuccess: () => {
            showClose.value = false;
            closeForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Tutup kasir" />

    <AuthenticatedLayout>
        <PageHeader
            title="Tutup kasir"
            subtitle="Rekap laci per shift: modal awal, kas masuk/keluar, setoran"
        >
            <template #action>
                <div v-if="current" class="flex flex-wrap gap-2">
                    <button class="btn-outline" @click="showCash = true">
                        Kas masuk / keluar
                    </button>
                    <button class="btn-primary rounded-xl" @click="showClose = true">
                        Tutup shift
                    </button>
                </div>
            </template>
        </PageHeader>

        <div
            v-if="autoClosed"
            class="card-amber mt-6 flex flex-wrap items-start gap-3 p-4"
        >
            <Icon name="clock" :size="20" class="mt-0.5 shrink-0 text-amber-ink" />
            <p class="text-body-md text-ink">
                Shift {{ autoClosed.kasir }} tanggal
                {{ autoClosed.opened_at }} <strong>ditutup otomatis</strong>
                karena ganti hari. Menurut sistem laci berisi
                <span class="num font-semibold">{{
                    rupiah(autoClosed.expected_cash)
                }}</span
                >, tapi uang fisiknya tidak sempat dihitung — jadi selisihnya
                tidak diketahui. Hitungan hari ini mulai dari nol.
            </p>
        </div>

        <!-- Belum ada shift terbuka -->
        <form
            v-if="!current"
            class="card-amber mt-6 max-w-lg p-5"
            @submit.prevent="openShift"
        >
            <h2 class="text-headline-sm text-ink">Buka shift</h2>
            <p class="mt-1 text-body-md text-ink-soft">
                Hitung uang di laci sebelum mulai jualan, lalu isikan sebagai
                modal awal. Transaksi yang tercatat setelah ini masuk ke shift
                tersebut.
            </p>

            <label class="label mb-1.5 mt-4 block" for="opening">
                Modal awal laci
            </label>
            <input
                id="opening"
                v-model.number="openForm.opening_cash"
                type="number"
                min="0"
                class="field num px-3 py-2.5"
            />
            <p
                v-if="openForm.errors.opening_cash"
                class="mt-1 text-2xs text-danger"
            >
                {{ openForm.errors.opening_cash }}
            </p>

            <label class="label mb-1.5 mt-3 block" for="onote">Catatan</label>
            <input
                id="onote"
                v-model="openForm.note"
                type="text"
                maxlength="255"
                class="field px-3 py-2.5 text-sm"
                placeholder="opsional"
            />

            <button class="btn-primary mt-4 w-full" :disabled="openForm.processing">
                Buka shift
            </button>
        </form>

        <!-- Shift berjalan -->
        <template v-else>
            <div class="card-brand mt-6 flex flex-wrap items-center gap-3 p-4">
                <Icon name="shift" :size="20" class="text-brand" />
                <p class="text-body-md text-ink">
                    Shift <strong>{{ current.kasir }}</strong> dibuka
                    {{ current.opened_at }}.
                </p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="card-slate p-5">
                    <div class="label">Modal awal</div>
                    <div class="num mt-2 text-headline-md text-ink">
                        {{ rupiah(current.opening_cash) }}
                    </div>
                </div>
                <div class="card-brand p-5">
                    <div class="label">Penjualan tunai</div>
                    <div class="num mt-2 text-headline-md text-brand-ink">
                        {{ rupiah(current.sales_tunai) }}
                    </div>
                    <div class="mt-1 text-2xs text-ink-soft">
                        {{ current.trx_count }} nota di shift ini
                    </div>
                </div>
                <div class="card-success p-5">
                    <div class="label">DP + pelunasan hutang</div>
                    <div class="num mt-2 text-headline-md text-success">
                        {{ rupiah(current.dp_kasbon + current.credit_payments) }}
                    </div>
                </div>
                <div class="card-amber p-5">
                    <div class="label">Kas masuk − keluar</div>
                    <div class="num mt-2 text-headline-md text-amber-ink">
                        {{ rupiah(current.cash_in - current.cash_out) }}
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="card-slate p-5">
                    <h2 class="text-headline-sm text-ink">Hitungan laci</h2>
                    <dl class="mt-3 space-y-1.5 text-body-md">
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Modal awal</dt>
                            <dd class="num">{{ rupiah(current.opening_cash) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Penjualan tunai</dt>
                            <dd class="num">{{ rupiah(current.sales_tunai) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">DP kasbon</dt>
                            <dd class="num">{{ rupiah(current.dp_kasbon) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Pelunasan hutang</dt>
                            <dd class="num">
                                {{ rupiah(current.credit_payments) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Kas masuk lain</dt>
                            <dd class="num">{{ rupiah(current.cash_in) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-soft">Kas keluar</dt>
                            <dd class="num text-danger">
                                −{{ rupiah(current.cash_out) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-line pt-2 text-headline-sm"
                        >
                            <dt>Seharusnya di laci</dt>
                            <dd class="num">{{ rupiah(current.expected_cash) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-2xs text-ink-soft">
                        Penjualan kasbon {{ rupiah(current.sales_kasbon) }} tidak
                        dihitung sebagai uang laci — hanya DP-nya.
                    </p>
                </div>

                <div class="card-amber overflow-hidden lg:col-span-2">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-headline-sm text-ink">
                            Kas masuk / keluar
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-body-md">
                            <thead>
                                <tr>
                                    <th class="th">Waktu</th>
                                    <th class="th">Arah</th>
                                    <th class="th">Keterangan</th>
                                    <th class="th text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr
                                    v-for="m in current.movements"
                                    :key="m.id"
                                    class="row-hover"
                                >
                                    <td class="td num text-ink-soft">
                                        {{ m.created_at }}
                                    </td>
                                    <td class="td">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-2xs font-semibold uppercase"
                                            :class="
                                                m.direction === 'masuk'
                                                    ? 'bg-success-wash text-success'
                                                    : 'bg-danger-wash text-danger'
                                            "
                                        >
                                            {{ m.direction }}
                                        </span>
                                    </td>
                                    <td class="td text-ink">{{ m.note }}</td>
                                    <td
                                        class="td num text-right font-medium"
                                        :class="
                                            m.direction === 'masuk'
                                                ? 'text-success'
                                                : 'text-danger'
                                        "
                                    >
                                        {{ m.direction === "masuk" ? "+" : "−"
                                        }}{{ rupiah(m.amount) }}
                                    </td>
                                </tr>
                                <tr v-if="!current.movements.length">
                                    <td
                                        colspan="4"
                                        class="td py-8 text-center text-ink-faint"
                                    >
                                        Belum ada kas masuk / keluar di shift ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Riwayat shift -->
        <div class="card-slate mt-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-headline-sm text-ink">Riwayat shift</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Kasir</th>
                            <th class="th">Buka</th>
                            <th class="th">Tutup</th>
                            <th class="th text-right">Modal</th>
                            <th class="th text-right">Seharusnya</th>
                            <th class="th text-right">Dihitung</th>
                            <th class="th text-right">Selisih</th>
                            <th class="th text-right">Setoran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="s in history.data" :key="s.id" class="row-hover">
                            <td class="td text-ink">{{ s.kasir }}</td>
                            <td class="td num text-ink-soft">{{ s.opened_at }}</td>
                            <td class="td num text-ink-soft">
                                {{ s.closed_at }}
                                <span
                                    v-if="s.auto_closed"
                                    class="ms-1 rounded-full bg-amber-wash px-1.5 py-0.5 font-sans text-2xs font-semibold uppercase text-amber-ink"
                                >
                                    otomatis
                                </span>
                            </td>
                            <td class="td num text-right">
                                {{ rupiah(s.opening_cash) }}
                            </td>
                            <td class="td num text-right">
                                {{ rupiah(s.expected_cash) }}
                            </td>
                            <td class="td num text-right">
                                <span v-if="s.counted_cash === null" class="text-ink-faint">
                                    —
                                </span>
                                <span v-else>{{ rupiah(s.counted_cash) }}</span>
                            </td>
                            <td
                                class="td num text-right font-semibold"
                                :class="
                                    s.difference === null
                                        ? 'text-ink-faint'
                                        : s.difference === 0
                                          ? 'text-ink-soft'
                                          : s.difference > 0
                                            ? 'text-success'
                                            : 'text-danger'
                                "
                            >
                                <span v-if="s.difference === null">tak dihitung</span>
                                <span v-else>
                                    {{ s.difference > 0 ? "+" : ""
                                    }}{{ rupiah(s.difference) }}
                                </span>
                            </td>
                            <td class="td num text-right text-ink-soft">
                                {{ s.deposit ? rupiah(s.deposit) : "—" }}
                            </td>
                        </tr>
                        <tr v-if="!history.data.length">
                            <td colspan="8" class="td py-10 text-center text-ink-faint">
                                Belum ada shift yang ditutup.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :paginator="history" noun="shift" />
        </div>

        <!-- Modal kas -->
        <Modal :show="showCash" max-width="md" @close="showCash = false">
            <form class="p-6" @submit.prevent="submitCash">
                <h2 class="text-headline-sm text-ink">Catat kas</h2>

                <div class="mt-4 grid grid-cols-2 gap-1 rounded-control bg-paper p-1">
                    <button
                        v-for="d in ['masuk', 'keluar']"
                        :key="d"
                        type="button"
                        class="rounded-control py-2 text-sm font-semibold capitalize transition-colors"
                        :class="
                            cashForm.direction === d
                                ? 'bg-brand text-white'
                                : 'text-ink-soft'
                        "
                        @click="cashForm.direction = d"
                    >
                        {{ d }}
                    </button>
                </div>

                <label class="label mb-1.5 mt-4 block" for="amount">Jumlah</label>
                <input
                    id="amount"
                    v-model.number="cashForm.amount"
                    type="number"
                    min="1"
                    class="field num px-3 py-2.5"
                />
                <p v-if="cashForm.errors.amount" class="mt-1 text-2xs text-danger">
                    {{ cashForm.errors.amount }}
                </p>

                <label class="label mb-1.5 mt-3 block" for="cnote">
                    Keterangan
                </label>
                <input
                    id="cnote"
                    v-model="cashForm.note"
                    type="text"
                    required
                    maxlength="255"
                    class="field px-3 py-2.5 text-sm"
                    placeholder="mis. beli plastik"
                />

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="showCash = false">
                        Batal
                    </button>
                    <button class="btn-primary" :disabled="cashForm.processing">
                        Simpan
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal tutup -->
        <Modal :show="showClose" max-width="md" @close="showClose = false">
            <form v-if="current" class="p-6" @submit.prevent="submitClose">
                <h2 class="text-headline-sm text-ink">Tutup shift</h2>
                <p class="mt-1 text-body-md text-ink-soft">
                    Hitung uang fisik di laci, lalu isikan di bawah. Sistem
                    menghitung selisihnya.
                </p>

                <div
                    class="mt-4 flex items-center justify-between rounded-control bg-brand-wash px-3 py-2.5"
                >
                    <span class="label">Seharusnya di laci</span>
                    <span class="num text-headline-sm text-brand-ink">
                        {{ rupiah(current.expected_cash) }}
                    </span>
                </div>

                <label class="label mb-1.5 mt-4 block" for="counted">
                    Uang fisik dihitung
                </label>
                <input
                    id="counted"
                    v-model.number="closeForm.counted_cash"
                    type="number"
                    min="0"
                    class="field num px-3 py-2.5"
                />

                <div
                    class="mt-3 flex items-center justify-between rounded-control px-3 py-2.5"
                    :class="
                        selisih === 0
                            ? 'bg-surface'
                            : selisih > 0
                              ? 'bg-success-wash'
                              : 'bg-danger-wash'
                    "
                >
                    <span class="label">Selisih</span>
                    <span
                        class="num font-semibold"
                        :class="
                            selisih === 0
                                ? 'text-ink-soft'
                                : selisih > 0
                                  ? 'text-success'
                                  : 'text-danger'
                        "
                    >
                        {{ selisih > 0 ? "+" : "" }}{{ rupiah(selisih) }}
                    </span>
                </div>

                <label class="label mb-1.5 mt-4 block" for="deposit">
                    Disetor ke pemilik
                </label>
                <input
                    id="deposit"
                    v-model.number="closeForm.deposit"
                    type="number"
                    min="0"
                    class="field num px-3 py-2.5"
                    placeholder="opsional"
                />

                <label class="label mb-1.5 mt-3 block" for="clnote">Catatan</label>
                <input
                    id="clnote"
                    v-model="closeForm.note"
                    type="text"
                    maxlength="255"
                    class="field px-3 py-2.5 text-sm"
                    placeholder="mis. selisih karena kembalian kurang"
                />

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="showClose = false">
                        Batal
                    </button>
                    <button class="btn-primary" :disabled="closeForm.processing">
                        Tutup shift
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
