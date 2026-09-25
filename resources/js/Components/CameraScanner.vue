<script setup>
// Scan barcode lewat kamera HP/laptop. Dipakai saat scanner USB tidak ada
// (mis. PC kasir mati) atau saat mengisi barcode produk dari HP.
//
// Chrome Android punya BarcodeDetector bawaan (cepat, hemat baterai);
// browser lain memakai ZXing yang baru dimuat saat dibutuhkan.
import { onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    // Tetap menyala setelah scan (kasir) atau berhenti di scan pertama (form).
    continuous: { type: Boolean, default: false },
    // Pesan hasil dari induk, mis. "Indomie masuk keranjang".
    feedback: { type: Object, default: null }, // { text, ok }
});
const emit = defineEmits(["detected"]);

const video = ref(null);
const status = ref("starting"); // starting | scanning | error
const errorText = ref("");
const torchAvailable = ref(false);
const torchOn = ref(false);

const FORMATS = ["ean_13", "ean_8", "upc_a", "upc_e", "code_128", "code_39", "code_93", "itf", "qr_code"];
const REPEAT_GAP_MS = 1500; // barcode yang sama diabaikan selama jeda ini

let stream = null;
let stopDecoder = null;
let stopped = false;
let lastCode = "";
let lastAt = 0;

function handle(code) {
    if (stopped || !code) return;
    const now = Date.now();
    if (code === lastCode && now - lastAt < REPEAT_GAP_MS) return;
    lastCode = code;
    lastAt = now;
    emit("detected", code);
    if (!props.continuous) stop();
}

async function startNative() {
    const supported = await window.BarcodeDetector.getSupportedFormats();
    const formats = FORMATS.filter((f) => supported.includes(f));
    if (!formats.length) return false;

    const detector = new window.BarcodeDetector({ formats });
    let timer = null;
    const tick = async () => {
        if (stopped) return;
        try {
            if (video.value?.readyState >= 2) {
                const [hit] = await detector.detect(video.value);
                if (hit?.rawValue) handle(hit.rawValue.trim());
            }
        } catch {
            // Frame gagal dibaca; coba frame berikutnya.
        }
        timer = setTimeout(tick, 120);
    };
    tick();
    stopDecoder = () => clearTimeout(timer);
    return true;
}

async function startZxing() {
    const { BrowserMultiFormatReader } = await import("@zxing/browser");
    const { BarcodeFormat, DecodeHintType } = await import("@zxing/library");
    const hints = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E, BarcodeFormat.CODE_128, BarcodeFormat.CODE_39,
            BarcodeFormat.CODE_93, BarcodeFormat.ITF, BarcodeFormat.QR_CODE,
        ]],
        [DecodeHintType.TRY_HARDER, true],
    ]);
    const reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 120 });
    const controls = await reader.decodeFromVideoElement(video.value, (result) => {
        if (result) handle(result.getText().trim());
    });
    stopDecoder = () => controls.stop();
}

async function start() {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.value = "error";
        errorText.value =
            "Kamera hanya bisa dipakai jika aplikasi dibuka lewat alamat HTTPS " +
            "(atau localhost). Hubungi pengelola aplikasi untuk mengaktifkan HTTPS.";
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: "environment" },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });
    } catch (e) {
        status.value = "error";
        errorText.value =
            e?.name === "NotAllowedError"
                ? "Izin kamera ditolak. Buka pengaturan situs di browser, izinkan Kamera, lalu coba lagi."
                : e?.name === "NotFoundError"
                  ? "Kamera tidak ditemukan di perangkat ini."
                  : "Kamera tidak bisa dibuka. Tutup aplikasi lain yang sedang memakai kamera, lalu coba lagi.";
        return;
    }
    if (stopped) return stop();

    video.value.srcObject = stream;
    await video.value.play().catch(() => {});

    const track = stream.getVideoTracks()[0];
    torchAvailable.value = !!track?.getCapabilities?.().torch;

    try {
        const native = "BarcodeDetector" in window && (await startNative());
        if (!native) await startZxing();
        status.value = "scanning";
    } catch {
        status.value = "error";
        errorText.value = "Pemindai barcode gagal dimuat. Muat ulang halaman lalu coba lagi.";
    }
}

async function toggleTorch() {
    const track = stream?.getVideoTracks()[0];
    if (!track) return;
    try {
        await track.applyConstraints({ advanced: [{ torch: !torchOn.value }] });
        torchOn.value = !torchOn.value;
    } catch {
        torchAvailable.value = false;
    }
}

function stop() {
    stopped = true;
    stopDecoder?.();
    stopDecoder = null;
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
}

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <div class="space-y-3">
        <div class="relative overflow-hidden rounded-xl bg-black aspect-[4/3]">
            <video
                ref="video"
                class="h-full w-full object-cover"
                muted
                playsinline
                autoplay
            />

            <!-- Bingkai bidik -->
            <div
                v-if="status === 'scanning'"
                class="pointer-events-none absolute inset-x-[12%] top-1/2 h-[38%] -translate-y-1/2 rounded-lg border-2 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"
            >
                <div class="absolute inset-x-2 top-1/2 h-0.5 -translate-y-1/2 bg-red-500/80" />
            </div>

            <div
                v-if="status === 'starting'"
                class="absolute inset-0 grid place-items-center text-sm text-white/80"
            >
                Membuka kamera…
            </div>
            <div
                v-if="status === 'error'"
                class="absolute inset-0 grid place-items-center bg-ink/90 p-5 text-center text-sm text-white"
            >
                {{ errorText }}
            </div>

            <button
                v-if="torchAvailable && status === 'scanning'"
                type="button"
                class="absolute right-3 top-3 rounded-full px-3 py-1.5 text-xs font-semibold"
                :class="torchOn ? 'bg-amber-300 text-ink' : 'bg-black/60 text-white'"
                @click="toggleTorch"
            >
                {{ torchOn ? "Senter ON" : "Senter" }}
            </button>
        </div>

        <p v-if="status === 'scanning' && !feedback" class="text-center text-xs text-ink-soft">
            Arahkan barcode ke dalam kotak. Jaga jarak ±10–20 cm dan pastikan cukup terang.
        </p>
        <p
            v-if="feedback"
            class="rounded-lg px-3 py-2 text-center text-sm font-semibold"
            :class="feedback.ok ? 'bg-brand-wash text-brand-ink' : 'bg-danger-wash text-danger'"
        >
            {{ feedback.text }}
        </p>
    </div>
</template>
