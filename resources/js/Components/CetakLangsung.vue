<script setup>
// Tombol cetak langsung ESC/POS tanpa dialog cetak:
// - Bluetooth (BLE): ponsel Android dan Chrome/Edge PC;
// - USB / COM (Web Serial): PC dengan printer USB-serial atau printer
//   Bluetooth klasik yang sudah dipasangkan di Windows.
// Tombol hanya muncul bila perambannya mendukung jalur itu.
import { ref } from "vue";
import Icon from "@/Components/Icon.vue";
import { cetakKeBluetooth, didukung } from "@/lib/printer-bluetooth";
import { cetakKeSerial, serialDidukung } from "@/lib/printer-serial";

const props = defineProps({
    // Fungsi pembuat byte ESC/POS, dipanggil saat tombol ditekan.
    buat: { type: Function, required: true },
    berhasil: { type: String, default: "Struk terkirim ke printer." },
});

const adaBluetooth = didukung();
const adaSerial = serialDidukung();
const sibuk = ref(null); // null | "bluetooth" | "serial"
const kabar = ref("");

async function cetak(jalur) {
    sibuk.value = jalur;
    kabar.value = jalur === "bluetooth" ? "Menyambung ke printer Bluetooth…" : "Membuka port printer…";

    try {
        const bytes = props.buat();
        await (jalur === "bluetooth" ? cetakKeBluetooth(bytes) : cetakKeSerial(bytes));
        kabar.value = props.berhasil;
    } catch (e) {
        kabar.value =
            e?.name === "NotFoundError"
                ? "Tidak ada printer yang dipilih."
                : `Gagal mencetak: ${e?.message ?? e}`;
    } finally {
        sibuk.value = null;
    }
}
</script>

<template>
    <div v-if="adaBluetooth || adaSerial" class="flex flex-col gap-2 print:hidden">
        <div class="flex flex-wrap gap-2">
            <button
                v-if="adaBluetooth"
                type="button"
                class="btn-primary flex-1 whitespace-nowrap"
                :disabled="!!sibuk"
                @click="cetak('bluetooth')"
            >
                <Icon name="print" :size="18" />
                {{ sibuk === "bluetooth" ? "Mencetak…" : "Printer Bluetooth" }}
            </button>
            <button
                v-if="adaSerial"
                type="button"
                class="flex-1 whitespace-nowrap"
                :class="adaBluetooth ? 'btn-secondary' : 'btn-primary'"
                :disabled="!!sibuk"
                title="Printer USB atau Bluetooth yang sudah dipasangkan di Windows (port COM)"
                @click="cetak('serial')"
            >
                <Icon name="print" :size="18" />
                {{ sibuk === "serial" ? "Mencetak…" : "Printer USB / COM" }}
            </button>
        </div>
        <p v-if="kabar" class="text-center text-xs text-ink-soft">{{ kabar }}</p>
    </div>
</template>
