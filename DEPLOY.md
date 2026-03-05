# Deployment Guide

Dashboard ini dapat di-deploy ke standard server (VPS / Shared Hosting yang mendukung PHP 8.2 & Node).

## 1. Persiapan Server
1. Instal PHP 8.2+
2. Instal Composer
3. Instal Node.js 18+ & NPM
4. Database relasional opsional (tidak ada model auth bawaan di versi ini, tapi Laravel mendukung SQLite/MySQL).

## 2. Pull Code & Build
```bash
git clone https://your-repository.git /var/www/market_pro
cd /var/www/market_pro

# Install Backend dependencies
composer install --optimize-autoloader --no-dev

# Setup environment config
cp .env.example .env
php artisan key:generate

# Build frontend assets (Vite)
npm install
npm run build
```

## 3. Konfigurasi Web Server (Nginx)
Arahkan document root ke folder `/public`.

```nginx
server {
    listen 80;
    server_name dashboard.yourdomain.com;
    root /var/www/market_pro/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 4. Keamanan
- Pastikan `/storage` dan `/bootstrap/cache` writable dengan permission 775.`chmod -R 775 storage bootstrap/cache`
- Aktifkan SSL (Let's Encrypt).
- Ubah `.env` dan set `APP_DEBUG=false`.
- Jalankan `php artisan optimize`
