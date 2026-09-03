<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
    store: Object,
});

const form = useForm({
    store_name: props.store.store_name ?? "",
    store_address: props.store.store_address ?? "",
    store_phone: props.store.store_phone ?? "",
    receipt_footer: props.store.receipt_footer ?? "",
});

function submit() {
    form.patch(route("settings.update"), { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan toko" />

    <AuthenticatedLayout>
        <PageHeader
            title="Pengaturan toko"
            subtitle="Identitas toko yang dipakai di struk dan tampilan aplikasi"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <form class="card-slate p-5 lg:col-span-2" @submit.prevent="submit">
                <label class="label mb-1.5 block" for="name">Nama toko</label>
                <input
                    id="name"
                    v-model="form.store_name"
                    type="text"
                    maxlength="60"
                    required
                    class="field px-3 py-2.5"
                />
                <p v-if="form.errors.store_name" class="mt-1 text-2xs text-danger">
                    {{ form.errors.store_name }}
                </p>

                <label class="label mb-1.5 mt-4 block" for="addr">Alamat</label>
                <input
                    id="addr"
                    v-model="form.store_address"
                    type="text"
                    maxlength="255"
                    class="field px-3 py-2.5"
                    placeholder="mis. Jl. Melati No. 12, Bandung"
                />

                <label class="label mb-1.5 mt-4 block" for="phone">Telepon</label>
                <input
                    id="phone"
                    v-model="form.store_phone"
                    type="text"
                    maxlength="30"
                    class="field num px-3 py-2.5"
                    placeholder="mis. 0812-3456-7890"
                />

                <label class="label mb-1.5 mt-4 block" for="footer">
                    Kaki struk
                </label>
                <input
                    id="footer"
                    v-model="form.receipt_footer"
                    type="text"
                    maxlength="255"
                    class="field px-3 py-2.5"
                    placeholder="mis. Terima kasih telah berbelanja."
                />

                <button class="btn-primary mt-5" :disabled="form.processing">
                    Simpan pengaturan
                </button>
            </form>

            <!-- Contoh tampilan struk -->
            <div class="card-brand p-5">
                <p class="label">Pratinjau struk</p>
                <div class="tape mt-3 rounded-card border border-line px-4 pb-4 text-center">
                    <p class="text-headline-sm font-bold text-ink">
                        {{ form.store_name || "Nama toko" }}
                    </p>
                    <p v-if="form.store_address" class="mt-1 text-2xs text-ink-soft">
                        {{ form.store_address }}
                    </p>
                    <p v-if="form.store_phone" class="num text-2xs text-ink-soft">
                        {{ form.store_phone }}
                    </p>
                    <div class="tape-rule my-3" />
                    <p class="num text-2xs text-ink-faint">INV20260903-0001</p>
                    <div class="tape-rule my-3" />
                    <p class="text-2xs text-ink-soft">
                        {{ form.receipt_footer }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
