<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Icon from '@/Components/Icon.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ categories: Array });

const form = useForm({ name: '' });
const editId = ref(null);
const editName = ref('');

function add() {
    form.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function startEdit(c) {
    editId.value = c.id;
    editName.value = c.name;
}

function saveEdit() {
    router.put(
        route('categories.update', editId.value),
        { name: editName.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editId.value = null;
            },
        },
    );
}

function destroy(c) {
    if (confirm(`Hapus kategori "${c.name}"?`)) {
        router.delete(route('categories.destroy', c.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kategori" />

    <AuthenticatedLayout>
        <PageHeader
            title="Kategori"
            subtitle="Kelompok produk untuk memfilter di kasir"
        />

        <div class="mt-6 max-w-lg">
            <form @submit.prevent="add">
                <label for="new-category" class="label mb-2">
                    Nama kategori baru
                </label>
                <div class="flex gap-2">
                    <input
                        id="new-category"
                        v-model="form.name"
                        placeholder="mis. Minuman"
                        class="field flex-1 py-2.5 text-sm"
                    />
                    <button :disabled="form.processing" class="btn-primary">
                        <Icon name="plus" :size="18" />
                        {{ form.processing ? "Menyimpan…" : "Tambah" }}
                    </button>
                </div>
            </form>
            <p v-if="form.errors.name" class="mt-2 text-xs text-danger">
                {{ form.errors.name }}
            </p>

            <div class="card-brand mt-4 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead>
                            <tr>
                                <th class="th">Nama kategori</th>
                                <th class="th text-right">Jumlah produk</th>
                                <th class="th text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in categories"
                                :key="c.id"
                                class="row-hover border-b border-line last:border-0"
                            >
                                <td class="td font-medium text-ink">
                                    <input
                                        v-if="editId === c.id"
                                        v-model="editName"
                                        @keyup.enter="saveEdit"
                                        class="field py-1.5 text-sm"
                                    />
                                    <template v-else>{{ c.name }}</template>
                                </td>
                                <td class="td num text-right text-ink-faint">
                                    {{ c.products_count }}
                                </td>
                                <td class="td whitespace-nowrap text-right">
                                    <template v-if="editId === c.id">
                                        <button @click="saveEdit" class="link">
                                            Simpan
                                        </button>
                                        <button
                                            @click="editId = null"
                                            class="ms-3 font-medium text-ink-faint hover:text-ink"
                                        >
                                            Batal
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button @click="startEdit(c)" class="link">
                                            Ubah
                                        </button>
                                        <button
                                            @click="destroy(c)"
                                            class="ms-3 font-medium text-danger hover:underline"
                                        >
                                            Hapus
                                        </button>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="!categories.length">
                                <td
                                    colspan="3"
                                    class="td py-10 text-center text-ink-faint"
                                >
                                    Belum ada kategori.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
