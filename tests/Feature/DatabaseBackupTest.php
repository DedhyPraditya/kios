<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up created test backups
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'backup-kios-berkah-')) {
                    File::delete($file->getPathname());
                }
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_view_backups_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups')
                ->has('stats')
            );
    }

    public function test_kasir_cannot_view_backups_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)
            ->get(route('backups.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_download_and_delete_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Create backup
        $this->actingAs($admin)
            ->post(route('backups.store'))
            ->assertSessionHasNoErrors();

        $service = app(DatabaseBackupService::class);
        $backups = $service->getBackups();
        $this->assertNotEmpty($backups);

        $filename = $backups[0]['filename'];

        // Verify activity log recorded
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'backup.create',
            'user_id' => $admin->id,
        ]);

        // 2. Download backup
        $this->actingAs($admin)
            ->get(route('backups.download', ['filename' => $filename]))
            ->assertOk();

        // 3. Delete backup
        $this->actingAs($admin)
            ->delete(route('backups.destroy', ['filename' => $filename]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(file_exists(storage_path("app/backups/{$filename}")));
    }

    public function test_restore_fails_with_invalid_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => bcrypt('correct-password'),
        ]);

        $service = app(DatabaseBackupService::class);
        $backup = $service->createBackup();

        $this->actingAs($admin)
            ->post(route('backups.restore'), [
                'filename' => $backup['filename'],
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_restore_succeeds_with_valid_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => bcrypt('secret123'),
        ]);

        $service = app(DatabaseBackupService::class);
        $backup = $service->createBackup();

        $this->actingAs($admin)
            ->post(route('backups.restore'), [
                'filename' => $backup['filename'],
                'password' => 'secret123',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'backup.restore',
            'user_id' => $admin->id,
        ]);
    }
}
