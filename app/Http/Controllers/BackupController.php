<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class BackupController extends Controller
{
    public function __construct(protected DatabaseBackupService $backupService)
    {
    }

    public function index()
    {
        return Inertia::render('Backup/Index', [
            'backups' => $this->backupService->getBackups(),
            'stats' => $this->backupService->getDatabaseStats(),
        ]);
    }

    public function store()
    {
        try {
            $backup = $this->backupService->createBackup();

            ActivityLog::record(
                'backup.create',
                "Membuat cadangan basis data '{$backup['filename']}' ({$backup['size']})",
                null,
                $backup
            );

            return back()->with('success', "Cadangan basis data '{$backup['filename']}' berhasil dibuat.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membuat cadangan: '.$e->getMessage());
        }
    }

    public function download(string $filename)
    {
        try {
            $path = $this->backupService->getBackupPath($filename);

            ActivityLog::record('backup.download', "Mengunduh berkas cadangan '{$filename}'");

            return response()->download($path);
        } catch (Throwable $e) {
            return back()->with('error', 'Berkas cadangan tidak ditemukan.');
        }
    }

    public function destroy(string $filename)
    {
        try {
            $this->backupService->deleteBackup($filename);

            ActivityLog::record('backup.delete', "Menghapus berkas cadangan '{$filename}'");

            return back()->with('success', "Berkas cadangan '{$filename}' berhasil dihapus.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menghapus berkas: '.$e->getMessage());
        }
    }

    public function restore(Request $request)
    {
        $data = $request->validate([
            'filename' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi akun Anda tidak sesuai. Pemulihan dibatalkan.',
            ]);
        }

        try {
            $this->backupService->restoreBackup($data['filename']);

            ActivityLog::record(
                'backup.restore',
                "Memulihkan basis data dari berkas cadangan '{$data['filename']}'",
                null,
                ['filename' => $data['filename']]
            );

            return back()->with('success', "Basis data berhasil dipulihkan dari '{$data['filename']}'.");
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal memulihkan basis data: '.$e->getMessage());
        }
    }
}
