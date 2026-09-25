// Bunyi pendek penanda hasil scan: nada tinggi = berhasil, nada rendah = gagal.
export function beep(ok = true) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = ok ? "sine" : "square";
        osc.frequency.value = ok ? 1200 : 220;
        gain.gain.value = ok ? 0.12 : 0.08;
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + (ok ? 0.08 : 0.25));
        osc.onended = () => ctx.close();
    } catch {
        // Browser tanpa Web Audio: cukup tanda di layar.
    }
    try {
        navigator.vibrate?.(ok ? 60 : [80, 60, 80]);
    } catch {
        // Getar tidak didukung.
    }
}
