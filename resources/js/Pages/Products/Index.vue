<script setup>
import { reactive, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Pagination from "@/Components/Pagination.vue";
import StockBadge from "@/Components/StockBadge.vue";
import Modal from "@/Components/Modal.vue";
import Icon from "@/Components/Icon.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { rupiah } from "@/lib/format";

const props = defineProps({
    products: Object,
    categories: Array,
    filters: Object,
});

const q = reactive({
    search: props.filters.search,
    category: props.filters.category ?? "",
    status: props.filters.status ?? "semua",
    sort: props.filters.sort ?? "nama",
});

let timer = null;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route("products.index"),
            {
                search: q.search || undefined,
                category: q.category || undefined,
                status: q.status !== "semua" ? q.status : undefined,
                sort: q.sort !== "nama" ? q.sort : undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    name: "",
    category_id: null,
    barcode: "",
    price: 0,
    cost: 0,
    stock: 0,
    low_stock: 10,
    is_active: true,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}
function openEdit(p) {
    editing.value = p;
    form.clearErrors();
    Object.assign(form, {
        name: p.name,
        category_id: p.category_id,
        barcode: p.barcode ?? "",
        price: p.price,
        cost: p.cost,
        stock: p.stock,
        low_stock: p.low_stock,
        is_active: p.is_active,
    });
    showModal.value = true;
}
function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };
    editing.value
        ? form.put(route("products.update", editing.value.id), opts)
        : form.post(route("products.store"), opts);
}
function destroy(p) {
    if (confirm(`Hapus produk "${p.name}"?`)) {
        router.delete(route("products.destroy", p.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Produk" />

    <AuthenticatedLayout>
        <PageHeader
            title="Kelola Produk"
            subtitle="Atur inventaris dan informasi produk Anda"
        >
            <template #action>
                <button @click="openCreate" class="btn-primary rounded-xl">
                    <Icon name="plus" :size="18" /> Tambah Produk
                </button>
            </template>
        </PageHeader>

        <!-- Toolbar -->
        <div class="card-slate mt-6 flex flex-wrap items-center gap-3 p-3">
            <div class="relative min-w-56 flex-1">
                <Icon
                    name="search"
                    :size="17"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
                />
                <input
                    v-model="q.search"
                    type="text"
                    placeholder="Cari nama produk atau barcode…"
                    class="field w-full rounded-xl py-2.5 pl-10 text-sm"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <select v-model="q.category" class="filter-pill">
                    <option value="">Semua kategori</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <select v-model="q.status" class="filter-pill">
                    <option value="semua">Semua stok</option>
                    <option value="menipis">Stok menipis</option>
                    <option value="habis">Stok habis</option>
                </select>
                <select v-model="q.sort" class="filter-pill">
                    <option value="nama">Urut: Nama</option>
                    <option value="harga_asc">Harga termurah</option>
                    <option value="harga_desc">Harga termahal</option>
                    <option value="stok">Stok tersedikit</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="card-brand mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-md">
                    <thead>
                        <tr>
                            <th class="th">Nama produk</th>
                            <th class="th">Kategori</th>
                            <th class="th text-right">Harga (Rp)</th>
                            <th class="th text-right">Stok</th>
                            <th class="th text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            class="row-hover border-b border-line last:border-0"
                        >
                            <td class="td font-medium">
                                <button
                                    class="link text-left"
                                    @click="openEdit(p)"
                                >
                                    {{ p.name }}
                                </button>
                                <span
                                    v-if="!p.is_active"
                                    class="ms-2 rounded bg-surface px-1.5 py-0.5 text-2xs uppercase text-ink-faint"
                                    >nonaktif</span
                                >
                            </td>
                            <td class="td text-brand">
                                {{ p.category?.name ?? "—" }}
                            </td>
                            <td class="td num text-right text-ink">
                                {{ rupiah(p.price) }}
                            </td>
                            <td class="td text-right">
                                <StockBadge
                                    :stock="p.stock"
                                    :low-stock="p.low_stock"
                                />
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                <button @click="openEdit(p)" class="link">
                                    Ubah
                                </button>
                                <button
                                    @click="destroy(p)"
                                    class="ms-3 font-medium text-danger hover:underline"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!products.data.length">
                            <td
                                colspan="5"
                                class="td py-12 text-center text-ink-faint"
                            >
                                Tidak ada produk yang cocok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :paginator="products" noun="produk" />
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-headline-sm text-ink">
                    {{ editing ? "Ubah produk" : "Produk baru" }}
                </h2>
                <div class="mt-5 grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label for="prod-nama" class="label">Nama</label>
                        <input id="prod-nama"
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
                        <label for="prod-kategori" class="label">Kategori</label>
                        <select id="prod-kategori"
                            v-model="form.category_id"
                            class="field mt-1 py-2 text-sm"
                        >
                            <option :value="null">— tanpa —</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="prod-barcode" class="label">Barcode</label>
                        <input id="prod-barcode"
                            v-model="form.barcode"
                            class="field num mt-1 py-2 text-sm"
                        />
                        <p
                            v-if="form.errors.barcode"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ form.errors.barcode }}
                        </p>
                    </div>
                    <div>
                        <label for="prod-harga-modal" class="label">Harga modal</label>
                        <input id="prod-harga-modal"
                            v-model="form.cost"
                            type="number"
                            min="0"
                            class="field num mt-1 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label for="prod-harga-jual" class="label">Harga jual</label>
                        <input id="prod-harga-jual"
                            v-model="form.price"
                            type="number"
                            min="0"
                            class="field num mt-1 py-2 text-sm"
                        />
                        <p
                            v-if="form.errors.price"
                            class="mt-1 text-xs text-danger"
                        >
                            {{ form.errors.price }}
                        </p>
                    </div>
                    <div>
                        <label for="prod-stok" class="label">Stok</label>
                        <input id="prod-stok"
                            v-model="form.stock"
                            type="number"
                            class="field num mt-1 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label for="prod-ambang-menipis" class="label">Ambang menipis</label>
                        <input id="prod-ambang-menipis"
                            v-model="form.low_stock"
                            type="number"
                            min="0"
                            class="field num mt-1 py-2 text-sm"
                        />
                    </div>
                    <label
                        class="col-span-2 flex items-center gap-2 text-sm text-ink-soft"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-line text-brand focus:ring-brand/20"
                        />
                        Aktif — tampil di layar kasir
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

<style scoped>
.filter-pill {
    @apply cursor-pointer appearance-none rounded-control border border-line bg-surface py-2 pl-3 pr-8 text-sm font-medium text-ink-soft transition-colors hover:text-ink focus:border-brand focus:outline-none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
}
</style>
