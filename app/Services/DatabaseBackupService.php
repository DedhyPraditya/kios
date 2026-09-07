<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (! File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Buat file cadangan database baru (.sql.gz).
     */
    public function createBackup(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup-kios-berkah-{$timestamp}.sql.gz";
        $filepath = $this->backupDir.DIRECTORY_SEPARATOR.$filename;

        $sql = "-- ====================================================\n";
        $sql .= "-- Kios BERKAH Database Backup\n";
        $sql .= "-- Waktu Pembuatan: ".now()->format('Y-m-d H:i:s')."\n";
        $sql .= "-- Driver: {$driver}\n";
        $sql .= "-- ====================================================\n\n";

        if ($driver === 'mysql') {
            $sql .= $this->dumpMySql();
        } elseif ($driver === 'sqlite') {
            $sql .= $this->dumpSqlite();
        } else {
            throw new RuntimeException("Driver database '{$driver}' tidak didukung untuk pencadangan.");
        }

        $compressed = gzencode($sql, 9);
        if ($compressed === false) {
            throw new RuntimeException('Gagal mengompresi cadangan basis data.');
        }

        File::put($filepath, $compressed);

        return [
            'filename' => $filename,
            'size' => $this->formatBytes(strlen($compressed)),
            'size_bytes' => strlen($compressed),
            'created_at' => now()->format('d M Y, H:i:s'),
        ];
    }

    /**
     * Ambil daftar seluruh berkas cadangan yang tersimpan.
     */
    public function getBackups(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (str_ends_with($name, '.sql.gz') || str_ends_with($name, '.sql')) {
                $size = $file->getSize();
                $modifiedTime = $file->getMTime();

                $backups[] = [
                    'filename' => $name,
                    'size' => $this->formatBytes($size),
                    'size_bytes' => $size,
                    'created_at' => date('d M Y, H:i:s', $modifiedTime),
                    'timestamp' => $modifiedTime,
                ];
            }
        }

        usort($backups, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Dapatkan path absolut file backup yang aman.
     */
    public function getBackupPath(string $filename): string
    {
        $cleanName = basename($filename);
        if ($cleanName !== $filename || (! str_ends_with($filename, '.sql.gz') && ! str_ends_with($filename, '.sql'))) {
            throw new RuntimeException('Nama berkas cadangan tidak valid.');
        }

        $path = $this->backupDir.DIRECTORY_SEPARATOR.$cleanName;
        if (! File::exists($path)) {
            throw new RuntimeException("Berkas cadangan '{$cleanName}' tidak ditemukan.");
        }

        return $path;
    }

    /**
     * Hapus berkas cadangan dari disk.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getBackupPath($filename);

        return File::delete($path);
    }

    /**
     * Pulihkan (restore) basis data dari file cadangan.
     */
    public function restoreBackup(string $filename): void
    {
        $path = $this->getBackupPath($filename);
        $raw = File::get($path);

        if (str_ends_with($filename, '.sql.gz')) {
            $sql = gzdecode($raw);
            if ($sql === false) {
                throw new RuntimeException('Gagal mendekompresi berkas cadangan .sql.gz.');
            }
        } else {
            $sql = $raw;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared($sql);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::unprepared($sql);
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::unprepared($sql);
        }
    }

    /**
     * Dapatkan statistik database saat ini.
     */
    public function getDatabaseStats(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $dbName = $connection->getDatabaseName();
            $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tableCount = count($tables);

            $sizeQuery = DB::selectOne("
                SELECT SUM(data_length + index_length) AS size_bytes
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$dbName]);

            $sizeBytes = (int) ($sizeQuery->size_bytes ?? 0);

            return [
                'driver' => 'MySQL',
                'database' => $dbName,
                'tables_count' => $tableCount,
                'size' => $this->formatBytes($sizeBytes),
                'size_bytes' => $sizeBytes,
            ];
        } elseif ($driver === 'sqlite') {
            $dbPath = $connection->getDatabaseName();
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $sizeBytes = file_exists($dbPath) ? filesize($dbPath) : 0;

            return [
                'driver' => 'SQLite',
                'database' => basename($dbPath),
                'tables_count' => count($tables),
                'size' => $this->formatBytes($sizeBytes),
                'size_bytes' => $sizeBytes,
            ];
        }

        return [
            'driver' => ucfirst($driver),
            'database' => $connection->getDatabaseName(),
            'tables_count' => 0,
            'size' => '—',
            'size_bytes' => 0,
        ];
    }

    /**
     * Dump skema dan baris data MySQL ke string SQL.
     */
    protected function dumpMySql(): string
    {
        $pdo = DB::connection()->getPdo();
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tablesResult = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $dbName = DB::connection()->getDatabaseName();
        $tableKey = 'Tables_in_'.$dbName;

        foreach ($tablesResult as $row) {
            $table = $row->$tableKey ?? current((array) $row);

            $sql .= "-- ----------------------------------------------------\n";
            $sql .= "-- Struktur Tabel: `{$table}`\n";
            $sql .= "-- ----------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $createResult = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createKey = 'Create Table';
            $sql .= ($createResult->$createKey ?? current((array) $createResult)).";\n\n";

            // Baris Data
            $count = DB::table($table)->count();
            if ($count > 0) {
                $sql .= "-- Data Tabel: `{$table}`\n";
                $cursor = DB::table($table)->orderBy(DB::raw('1'))->cursor();
                $chunk = [];

                foreach ($cursor as $dataRow) {
                    $values = [];
                    foreach ((array) $dataRow as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($val) && ! is_string($val)) {
                            $values[] = $val;
                        } else {
                            $values[] = $pdo->quote((string) $val);
                        }
                    }
                    $chunk[] = '('.implode(', ', $values).')';

                    if (count($chunk) >= 200) {
                        $sql .= "INSERT INTO `{$table}` VALUES \n".implode(",\n", $chunk).";\n";
                        $chunk = [];
                    }
                }

                if (! empty($chunk)) {
                    $sql .= "INSERT INTO `{$table}` VALUES \n".implode(",\n", $chunk).";\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * Dump skema dan baris data SQLite ke string SQL.
     */
    protected function dumpSqlite(): string
    {
        $pdo = DB::connection()->getPdo();
        $sql = "PRAGMA foreign_keys = OFF;\n\n";

        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $t) {
            $table = $t->name;
            $sql .= "-- ----------------------------------------------------\n";
            $sql .= "-- Struktur Tabel: `{$table}`\n";
            $sql .= "-- ----------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS \"{$table}\";\n";
            $sql .= $t->sql.";\n\n";

            $cursor = DB::table($table)->cursor();
            $chunk = [];

            foreach ($cursor as $dataRow) {
                $values = [];
                foreach ((array) $dataRow as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($val) && ! is_string($val)) {
                        $values[] = $val;
                    } else {
                        $values[] = $pdo->quote((string) $val);
                    }
                }
                $chunk[] = '('.implode(', ', $values).')';

                if (count($chunk) >= 200) {
                    $sql .= "INSERT INTO \"{$table}\" VALUES \n".implode(",\n", $chunk).";\n";
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $sql .= "INSERT INTO \"{$table}\" VALUES \n".implode(",\n", $chunk).";\n";
            }
            $sql .= "\n";
        }

        $sql .= "PRAGMA foreign_keys = ON;\n";

        return $sql;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
