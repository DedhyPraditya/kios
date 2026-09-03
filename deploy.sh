#!/bin/bash

# ============================================
# Script Deploy Kios Berkah - aaPanel
# Jalankan di server: bash deploy.sh
# ============================================

set -e  # Stop jika ada error

echo "🚀 Mulai proses deploy Kios Berkah..."
echo "======================================"

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
echo "🔒 Set permission..."
chown -R www:www /www/wwwroot/kios 2>/dev/null || true
chmod -R 755 /www/wwwroot/kios 2>/dev/null || true
chmod -R 777 /www/wwwroot/kios/storage 2>/dev/null || true
chmod -R 777 /www/wwwroot/kios/bootstrap/cache 2>/dev/null || true

echo ""
echo "======================================"
echo "✅ Deploy berhasil!"
echo "🌐 Silakan buka website Anda di browser"
echo "======================================"
