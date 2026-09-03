<script setup>
import { ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters.search);
let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("customers.index"),
            { search: search.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    name: "",
    phone: "",
    address: "",
    credit_limit: null,
    is_blocked: false,
    note: "",
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}
function openEdit(c) {
    editing.value = c;
    form.clearErrors();
    Object.assign(form, {
        name: c.name,
        phone: c.phone ?? "",
        address: c.address ?? "",
        credit_limit: c.credit_limit,
        is_blocked: c.is_blocked,
        note: c.note ?? "",
    });
    showModal.value = true;
}
function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };
    editing.value
        ? form.put(route("customers.update", editing.value.id), opts)
        : form.post(route("customers.store"), opts);
}
function destroy(c) {
    if (confirm(`Hapus pelanggan "${c.name}"?`)) {
        router.delete(route("customers.destroy", c.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Pelanggan" />

    <AuthenticatedLayout>
        <PageHeader
            title="Pelanggan"
            subtitle="Data pelanggan dan sisa hutang kasbon"
        >
            <template #action>
                <button @click="openCreate" class="btn-primary rounded-xl">
                    <Icon name="plus" :size="18" /> Tambah Pelanggan
                </button>
            </template>
        </PageHeader>

        <div class="card-slate mt-6 flex flex-wrap items-center gap-3 p-3">
            <div class="relative min-w-56 flex-1">
                <Icon
                    name="search"
                    :size="17"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari nama atau telepon…"
                    class="field w-full rounded-xl py-2.5 pl-10 text-sm"
                />
            </div>
        </div>

        <div class="card-slate mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Nama</th>
                            <th class="th">Telepon</th>
                            <th class="th text-right">Sisa hutang</th>
                            <th class="th text-right">Batas kredit</th>
                            <th class="th">Status</th>
                            <th class="th"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in customers.data"
                            :key="c.id"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td font-medium text-ink">
                                <Link
                                    :href="route('customers.show', c.id)"
                                    class="link"
                                    >{{ c.name }}</Link
                                >
                            </td>
                            <td class="td num text-ink-soft">
                                {{ c.phone ?? "—" }}
                            </td>
                            <td
                                class="td num text-right font-semibold"
                                :class="
                                    c.outstanding > 0
                                        ? 'text-danger'
                                        : 'text-ink-faint'
                                "
                            >
                                {{ rupiah(c.outstanding) }}
                            </td>
                            <td class="td num text-right text-ink-soft">
                                {{
                                    c.credit_limit === null
                                        ? "tanpa batas"
                                        : rupiah(c.credit_limit)
                                }}
                            </td>
                            <td class="td">
                                <span
                                    v-if="c.is_blocked"
                                    class="rounded-full bg-danger-wash px-2 py-0.5 text-2xs font-semibold uppercase text-danger"
                                    >diblokir</span
                                >
                                <span
                                    v-else
                                    class="rounded-full bg-success-wash px-2 py-0.5 text-2xs font-semibold uppercase text-success"
                                    >aktif</span
                                >
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                <button @click="openEdit(c)" class="link">
                                    Ubah
                                </button>
                                <button
                                    @click="destroy(c)"
                                    class="ms-3 font-medium text-danger hover:underline"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!customers.data.length">
                            <td
                                colspan="6"
                                class="td py-10 text-center text-ink-faint"
                            >
                                Belum ada pelanggan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :paginator="customers" noun="pelanggan" />
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-headline-sm text-ink">
                    {{ editing ? "Ubah pelanggan" : "Pelanggan baru" }}
                </h2>
                <div class="mt-5 grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label for="cust-nama" class="label">Nama</label>
                        <input id="cust-nama"
                            v-model="form.name"
                            class="field mt-1 py-2 text-sm"
                        />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label for="cust-telepon" class="label">Telepon</label>
                        <input
                            id="cust-telepon"
                            v-model="form.phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            class="field num mt-1 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label for="cust-batas-kredit" class="label">Batas kredit</label>
                        <input id="cust-batas-kredit"
                            v-model="form.credit_limit"
                            type="number"
                            min="0"
                            placeholder="kosong = tanpa batas"
                            class="field num mt-1 py-2 text-sm"
                        />
                        <p
                            v-if="form.errors.credit_limit"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ form.errors.credit_limit }}
                        </p>
                    </div>
                    <div class="col-span-2">
                        <label for="cust-alamat" class="label">Alamat</label>
                        <input id="cust-alamat"
                            v-model="form.address"
                            class="field mt-1 py-2 text-sm"
                        />
                    </div>
                    <div class="col-span-2">
                        <label for="cust-catatan" class="label">Catatan</label>
                        <input id="cust-catatan"
                            v-model="form.note"
                            class="field mt-1 py-2 text-sm"
                        />
                    </div>
                    <label
                        class="col-span-2 flex items-center gap-2 text-sm text-ink-soft"
                    >
                        <input
                            v-model="form.is_blocked"
                            type="checkbox"
                            class="rounded border-line text-brand focus:ring-brand/20"
                        />
                        Blokir dari kasbon
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button @click="showModal = false" class="btn-ghost">
                        Batal
                    </button>
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="btn-primary"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
