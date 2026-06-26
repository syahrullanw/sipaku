#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; }
info() { echo -e "${CYAN}[i]${NC} $1"; }

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  SIPAKU SMK - Setup Otomatis${NC}"
echo -e "${CYAN}  v$(cat "$BASE_DIR/VERSION" 2>/dev/null || echo '?')${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# --------------------------------------------------
# Cek Prasyarat
# --------------------------------------------------
info "Memeriksa prasyarat..."

PHP_AVAILABLE=false
MYSQL_AVAILABLE=false
NODE_AVAILABLE=false

if command -v php &>/dev/null; then
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    log "PHP $PHP_VERSION"
    PHP_AVAILABLE=true
else
    err "PHP tidak ditemukan. Install PHP >= 8.1 dengan ekstensi PDO MySQL."
fi

if command -v mysql &>/dev/null; then
    MYSQL_VERSION=$(mysql --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' || echo "?")
    log "MySQL $MYSQL_VERSION"
    MYSQL_AVAILABLE=true
else
    warn "MySQL CLI tidak ditemukan. Pastikan MySQL/MariaDB sudah berjalan."
fi

if command -v node &>/dev/null; then
    NODE_VERSION=$(node -v 2>/dev/null || echo "?")
    log "Node.js $NODE_VERSION"
    NODE_AVAILABLE=true
    if command -v npm &>/dev/null; then
        log "npm $(npm -v)"
    fi
else
    warn "Node.js tidak ditemukan. Frontend tidak akan dibangun ulang."
fi

if [ "$PHP_AVAILABLE" = false ] && [ "$MYSQL_AVAILABLE" = false ]; then
    err "Tidak ada prasyarat yang terpenuhi. Silakan install PHP dan MySQL terlebih dahulu."
    exit 1
fi

echo ""

# --------------------------------------------------
# Konfigurasi Database
# --------------------------------------------------
info "Konfigurasi Database MySQL/MariaDB"
echo ""

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-sipaku}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

read -p "$(echo -e "${YELLOW}Host database${NC} [$DB_HOST]: ")" input && [ -n "$input" ] && DB_HOST="$input"
read -p "$(echo -e "${YELLOW}Port database${NC} [$DB_PORT]: ")" input && [ -n "$input" ] && DB_PORT="$input"
read -p "$(echo -e "${YELLOW}Nama database${NC} [$DB_NAME]: ")" input && [ -n "$input" ] && DB_NAME="$input"
read -p "$(echo -e "${YELLOW}Username database${NC} [$DB_USER]: ")" input && [ -n "$input" ] && DB_USER="$input"
read -s -p "$(echo -e "${YELLOW}Password database${NC} [$DB_PASS]: ")" input && echo "" && [ -n "$input" ] && DB_PASS="$input"

echo ""

# --------------------------------------------------
# Konfigurasi Aplikasi
# --------------------------------------------------
info "Konfigurasi Aplikasi"
APP_URL="${APP_URL:-http://localhost:8000}"
read -p "$(echo -e "${YELLOW}URL Aplikasi${NC} (contoh: http://localhost:8000/sipaku) [$APP_URL]: ")" input && [ -n "$input" ] && APP_URL="$input"

echo ""

# --------------------------------------------------
# Tulis Konfigurasi
# --------------------------------------------------
info "Menulis konfigurasi database..."
cat > "$BASE_DIR/app/Config/database.php" << DBPHP
<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '$DB_HOST',
            'port' => $DB_PORT,
            'database' => '$DB_NAME',
            'username' => '$DB_USER',
            'password' => '$DB_PASS',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ],
    ],
    'migrations_table' => 'migrations',
];
DBPHP
log "app/Config/database.php"

info "Menulis konfigurasi aplikasi..."
cat > "$BASE_DIR/app/Config/app.php" << APPPHP
<?php

return [
    'name' => 'SIPAKU SMK',
    'env' => 'production',
    'debug' => false,
    'url' => '$APP_URL',
    'timezone' => 'Asia/Jakarta',
    'locale' => 'id_ID',
    'log_channel' => 'single',
    'version' => '$(cat "$BASE_DIR/VERSION" 2>/dev/null || echo "1.0.0")',
];
APPPHP
log "app/Config/app.php"

echo ""

# --------------------------------------------------
# Setup Database
# --------------------------------------------------
info "Menyiapkan database..."

# Buat database jika belum ada
MYSQL_CMD="mysql -h $DB_HOST -P $DB_PORT -u $DB_USER"
[ -n "$DB_PASS" ] && MYSQL_CMD="$MYSQL_CMD -p$DB_PASS"

if $MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null; then
    log "Database '$DB_NAME' siap."
else
    warn "Gagal membuat database. Periksa kredensial MySQL."
    warn "Anda bisa membuat database secara manual lalu jalankan ulang script."
fi

echo ""

# --------------------------------------------------
# Import Schema
# --------------------------------------------------
info "Mengimpor skema database..."

if $MYSQL_CMD "$DB_NAME" < "$BASE_DIR/database/schema.sql" 2>/dev/null; then
    log "Skema database berhasil diimpor."
else
    warn "Gagal mengimpor schema.sql. Jalankan secara manual:"
    warn "  $MYSQL_CMD $DB_NAME < database/schema.sql"
fi

# --------------------------------------------------
# Jalankan Migrasi (urut berdasarkan timestamp)
# --------------------------------------------------
MIGRATIONS_DIR="$BASE_DIR/database/migrations"
if [ -d "$MIGRATIONS_DIR" ]; then
    info "Menjalankan migrasi database..."
    for mig_file in $(ls "$MIGRATIONS_DIR"/*.sql 2>/dev/null | sort); do
        mig_basename=$(basename "$mig_file")
        # Cek apakah migrasi sudah pernah dijalankan
        ALREADY_RUN=$($MYSQL_CMD -N -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`migrations\` WHERE migration = '$mig_basename';" 2>/dev/null || echo "0")
        if [ "$ALREADY_RUN" = "0" ]; then
            if $MYSQL_CMD "$DB_NAME" < "$mig_file" 2>/dev/null; then
                $MYSQL_CMD -e "INSERT INTO \`$DB_NAME\`.\`migrations\` (migration, batch) VALUES ('$mig_basename', 1);" 2>/dev/null || true
                log "  $mig_basename"
            else
                warn "  Gagal: $mig_basename"
            fi
        else
            info "  $mig_basename (sudah ada, dilewati)"
        fi
    done
else
    warn "Direktori migrations tidak ditemukan."
fi

echo ""

# --------------------------------------------------
# Buat Admin User
# --------------------------------------------------
ADMIN_EXISTS=$($MYSQL_CMD -N -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`users\` WHERE role = 'admin' LIMIT 1;" 2>/dev/null || echo "0")
if [ "$ADMIN_EXISTS" = "0" ]; then
    info "Membuat akun admin..."
    ADMIN_USER="${ADMIN_USER:-admin}"
    ADMIN_PASS="${ADMIN_PASS:-admin123}"
    ADMIN_EMAIL="${ADMIN_EMAIL:-admin@sekolah.sch.id}"

    read -p "$(echo -e "${YELLOW}Username admin${NC} [$ADMIN_USER]: ")" input && [ -n "$input" ] && ADMIN_USER="$input"
    read -s -p "$(echo -e "${YELLOW}Password admin${NC} [$ADMIN_PASS]: ")" input && echo "" && [ -n "$input" ] && ADMIN_PASS="$input"
    read -p "$(echo -e "${YELLOW}Email admin${NC} [$ADMIN_EMAIL]: ")" input && [ -n "$input" ] && ADMIN_EMAIL="$input"

    HASHED_PASS=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    $MYSQL_CMD -e "INSERT INTO \`$DB_NAME\`.\`users\` (name, username, password, email, role, created_at, updated_at) VALUES ('Administrator', '$ADMIN_USER', '$HASHED_PASS', '$ADMIN_EMAIL', 'admin', NOW(), NOW());" 2>/dev/null || {
        warn "Gagal membuat admin. Buat manual dengan query SQL."
    }
    log "Admin: username '$ADMIN_USER', password '$ADMIN_PASS'"
else
    info "Admin sudah ada, dilewati."
fi

echo ""

# --------------------------------------------------
# Setup Storage & Uploads
# --------------------------------------------------
info "Menyiapkan direktori penyimpanan..."
mkdir -p "$BASE_DIR/storage/cache"
mkdir -p "$BASE_DIR/storage/logs"
mkdir -p "$BASE_DIR/storage/backups"
mkdir -p "$BASE_DIR/storage/keuangan"
mkdir -p "$BASE_DIR/public/uploads/siswa"
mkdir -p "$BASE_DIR/public/uploads/sekolah"
mkdir -p "$BASE_DIR/public/uploads/surat-keluar"

# Coba set permission (kalau pakai Linux)
chmod -R 755 "$BASE_DIR/storage" 2>/dev/null && chmod -R 755 "$BASE_DIR/public/uploads" 2>/dev/null && log "Permission direktori storage diatur." || warn "Gagal set permission (mungkin bukan Linux)."
echo ""

# --------------------------------------------------
# Opsional: Build Frontend
# --------------------------------------------------
if [ "$NODE_AVAILABLE" = true ]; then
    info "Memasang dependensi frontend..."
    cd "$BASE_DIR"
    npm install 2>/dev/null && log "npm install selesai." || warn "npm install gagal."

    info "Membangun aset frontend..."
    npm run build 2>/dev/null && log "Frontend berhasil dibangun." || warn "npm run build gagal."
    echo ""
fi

# --------------------------------------------------
# Selesai
# --------------------------------------------------
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Setup SIPAKU SMK Selesai!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "  URL Aplikasi : ${CYAN}$APP_URL${NC}"
echo -e "  Login Admin   : ${CYAN}$ADMIN_USER${NC} / ${CYAN}$ADMIN_PASS${NC}"
echo ""
echo -e "  ${YELLOW}Langkah Selanjutnya:${NC}"
echo "  1. Jalankan server:"
echo "     ${CYAN}php -S localhost:8000 -t public${NC}"
echo ""
echo "  2. Atau deploy ke Apache/Nginx dengan document root ke direktori public/"
echo ""
echo "  3. (Wajib) Ganti password admin setelah login pertama!"
echo ""
echo "  4. Untuk production, matikan debug di app/Config/app.php:"
echo "     'debug' => false,"
echo ""
exit 0
