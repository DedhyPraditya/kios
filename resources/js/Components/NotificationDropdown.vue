<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import Icon from "@/Components/Icon.vue";
import { rupiah } from "@/lib/format";

const page = usePage();
const alerts = computed(() => page.props.alerts || {});
const isAdmin = computed(() => page.props.auth.isAdmin);

const lowStockCount = computed(() => alerts.value.lowStockCount ?? alerts.value.lowStock ?? 0);
const lowStockItems = computed(() => alerts.value.lowStockItems ?? []);

const dueDebtsCount = computed(() => alerts.value.dueDebtsCount ?? 0);
const dueDebtsItems = computed(() => alerts.value.dueDebtsItems ?? []);

const totalCount = computed(() => alerts.value.total ?? (lowStockCount.value + dueDebtsCount.value));

const open = ref(false);
const activeTab = ref("stock"); // 'stock' | 'debts'

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function closeOnEscape(e) {
    if (open.value && e.key === "Escape") {
        close();
    }
}

onMounted(() => document.addEventListener("keydown", closeOnEscape));
onUnmounted(() => document.removeEventListener("keydown", closeOnEscape));
</script>

<template>
    <div class="relative">
        <!-- Bell Trigger Button -->
        <button
            type="button"
            class="relative grid h-9 w-9 place-items-center rounded-control text-ink-soft transition-colors hover:bg-paper hover:text-ink focus:outline-none"
            :class="open ? 'bg-paper text-ink' : ''"
            :title="totalCount ? `${totalCount} pemberitahuan baru` : 'Pemberitahuan'"
            :aria-label="totalCount ? `${totalCount} pemberitahuan baru` : 'Pemberitahuan'"
            @click="toggle"
        >
            <Icon name="bell" :size="19" />
            <span
                v-if="totalCount > 0"
                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white ring-2 ring-surface"
            >
                {{ totalCount > 99 ? '99+' : totalCount }}
            </span>
        </button>

        <!-- Backdrop Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="close"
        />

        <!-- Notification Panel Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute end-0 z-50 mt-2 w-80 sm:w-96 rounded-card border border-line bg-surface shadow-sheet overflow-hidden"
            >
                <!-- Panel Header -->
                <div class="flex items-center justify-between border-b border-line bg-paper/60 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="text-body-md font-bold text-ink">Pemberitahuan</span>
                        <span
                            v-if="totalCount > 0"
                            class="rounded-full bg-danger/10 px-2 py-0.5 text-2xs font-bold text-danger"
                        >
                            {{ totalCount }} baru
                        </span>
                    </div>
                    <button
                        type="button"
                        class="text-ink-faint hover:text-ink text-xs transition-colors"
                        @click="close"
                    >
                        Tutup
                    </button>
                </div>

                <!-- Tabs -->
                <div class="grid grid-cols-2 border-b border-line text-xs font-semibold">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-1.5 py-2.5 transition-colors"
                        :class="
                            activeTab === 'stock'
                                ? 'border-b-2 border-brand bg-surface text-brand'
                                : 'bg-paper/40 text-ink-soft hover:bg-paper hover:text-ink'
                        "
                        @click="activeTab = 'stock'"
                    >
                        <span>Stok Menipis</span>
                        <span
                            v-if="lowStockCount > 0"
                            class="rounded-full px-1.5 py-0.2 text-[10px]"
                            :class="activeTab === 'stock' ? 'bg-brand/10 text-brand' : 'bg-line text-ink-soft'"
                        >
                            {{ lowStockCount }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-1.5 py-2.5 transition-colors"
                        :class="
                            activeTab === 'debts'
                                ? 'border-b-2 border-brand bg-surface text-brand'
                                : 'bg-paper/40 text-ink-soft hover:bg-paper hover:text-ink'
                        "
                        @click="activeTab = 'debts'"
                    >
                        <span>Kasbon Jatuh Tempo</span>
                        <span
                            v-if="dueDebtsCount > 0"
                            class="rounded-full px-1.5 py-0.2 text-[10px]"
                            :class="activeTab === 'debts' ? 'bg-amber/20 text-amber-900' : 'bg-line text-ink-soft'"
                        >
                            {{ dueDebtsCount }}
                        </span>
                    </button>
                </div>

                <!-- Tab Content: Stok Menipis -->
                <div v-if="activeTab === 'stock'" class="max-h-80 overflow-y-auto divide-y divide-line">
                    <div
                        v-if="lowStockItems.length === 0"
                        class="px-4 py-8 text-center text-body-md text-ink-faint"
                    >
                        <div class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-brand-wash text-brand">
                            ✓
                        </div>
                        Semua stok produk dalam batas aman.
                    </div>

                    <div
                        v-for="item in lowStockItems"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-paper/50 transition-colors"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-body-md font-medium text-ink">
                                {{ item.name }}
                            </p>
                            <p class="text-2xs text-ink-soft">
                                Batas minimum: <span class="num font-semibold">{{ item.low_stock }}</span>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold num"
                                :class="
                                    item.stock <= 0
                                        ? 'bg-danger-wash text-danger'
                                        : 'bg-amber-100 text-amber-900'
                                "
                            >
                                Sisa {{ item.stock }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Kasbon Jatuh Tempo -->
                <div v-else class="max-h-80 overflow-y-auto divide-y divide-line">
                    <div
                        v-if="dueDebtsItems.length === 0"
                        class="px-4 py-8 text-center text-body-md text-ink-faint"
                    >
                        <div class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-brand-wash text-brand">
                            ✓
                        </div>
                        Tidak ada kasbon yang mendekati atau lewat tempo.
                    </div>

                    <div
                        v-for="debt in dueDebtsItems"
                        :key="debt.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-paper/50 transition-colors"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="truncate text-body-md font-medium text-ink">
                                    {{ debt.customer_name }}
                                </p>
                                <span
                                    v-if="debt.is_overdue"
                                    class="rounded bg-danger/10 px-1.5 py-0.5 text-[10px] font-bold text-danger uppercase tracking-wider"
                                >
                                    Lewat tempo
                                </span>
                            </div>
                            <p class="text-2xs text-ink-soft">
                                Nota: <span class="num">{{ debt.invoice_no }}</span> • Tempo: {{ debt.due_date }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="block text-body-md font-bold text-danger num">
                                {{ rupiah(debt.outstanding) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Panel Footer Links -->
                <div class="border-t border-line bg-paper/60 p-2.5 text-center">
                    <Link
                        v-if="activeTab === 'stock'"
                        :href="isAdmin ? route('products.index', { low: 1 }) : route('dashboard')"
                        class="text-xs font-semibold text-brand hover:underline"
                        @click="close"
                    >
                        Lihat Semua Produk Menipis &rarr;
                    </Link>
                    <Link
                        v-else
                        :href="isAdmin ? route('piutang.index') : route('dashboard')"
                        class="text-xs font-semibold text-brand hover:underline"
                        @click="close"
                    >
                        Kelola Semua Piutang &rarr;
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
