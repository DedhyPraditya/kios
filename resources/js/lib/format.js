export function rupiah(value) {
    const n = Number(value || 0);
    return 'Rp' + n.toLocaleString('id-ID');
}

export function tanggal(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
