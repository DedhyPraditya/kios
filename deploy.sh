#!/bin/bash

# ============================================
# Script Deploy Kios Berkah - aaPanel
# Jalankan di server: bash deploy.sh
# ============================================

set -e  # Stop jika ada error

echo "🚀 Mulai proses deploy Kios Berkah..."
echo "======================================"

# Bit izin akses jangan dianggap perubahan berkas. Tanpa ini, chmod di bawah
# membuat seluruh berkas tercatat "modified" dan git pull berikutnya menolak
# jalan karena takut menimpa perubahan lokal.
git config core.fileMode false

# Lockfile kadang disentuh `npm install` di server; isi resminya ada di repo.
git checkout -- package-lock.json 2>/dev/null || true

# Pull perubahan dari GitHub
echo "📥 Pulling dari GitHub..."
git pull origin main

# Install/update PHP dependencies
echo "📦 Install Composer dependencies..."
composer install --optimize-autoloader --no-dev --quiet

# Install/update Node dependencies & build
echo "🔨 Build frontend assets..."
npm install --quiet
npm run build

# Jalankan migrasi database
echo "🗃️  Menjalankan migrasi database..."
php artisan migrate --force

# Clear & rebuild cache
echo "⚡ Optimasi cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permission
# Folder perlu 755 supaya bisa ditelusuri; berkas cukup 644 — memberi bit
# eksekusi ke berkas PHP tak ada gunanya dan hanya memperluas permukaan serang.
# Yang benar-benar ditulis aplikasi hanya storage dan bootstrap/cache.
echo "🔒 Set permission..."
# `.git`, `node_modules`, dan `vendor` sengaja dilewati: isinya diurus git,
# npm, dan composer sendiri, dan menyeragamkan berkas di sana ke 644 mencabut
# bit eksekusi dari perkakasnya (vite, phpunit, pint) sehingga build gagal.
APP_DIR=/www/wwwroot/kios
chown -R www:www "$APP_DIR" 2>/dev/null || true
find "$APP_DIR"     \( -path "$APP_DIR/.git" -o -path "$APP_DIR/node_modules" -o -path "$APP_DIR/vendor" \) -prune     -o -type d -exec chmod 755 {} + 2>/dev/null || true
find "$APP_DIR"     \( -path "$APP_DIR/.git" -o -path "$APP_DIR/node_modules" -o -path "$APP_DIR/vendor" \) -prune     -o -type f -exec chmod 644 {} + 2>/dev/null || true
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

echo ""
echo "======================================"
echo "✅ Deploy berhasil!"
echo "🌐 Silakan buka website Anda di browser"
echo "======================================"
