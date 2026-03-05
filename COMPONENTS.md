# Design System & Components Specification

Semua style diatur menggunakan variabe CSS di file `resources/css/app.css`. Kami menggunakan pendekatan "utility-first" namun tanpa overhead dari purgable utility seperti Tailwind standar, difokuskan pada semantic class untuk performa render maksimum.

## 1. Design Tokens (Colors)
- `--bg-base`: Background sangat gelap `#0a0e17`
- `--bg-surface`: Panel / card `#111827`
- `--text-primary`: `#e2e8f0` (Putih terang)
- `--accent`: `#f59e0b` (Amber / Emas gaya Bloomberg)
- `--success`: `#10b981` (Hijau bullish)
- `--danger`: `#ef4444` (Merah bearish)

## 2. Layout Grid Shell
- `.app-layout`: Wrapper utama dengan CSS Grid (sidebar, topbar, konten, status bar).

## 3. Komponen Dasar
### Buttons
- `.btn`: Base style button
- `.btn-primary`: Tombol aksi (warna emas)
- `.btn-ghost`: Transparan dengan border
- `.btn-sm`: Tombol kecil

### Badges
- `.badge`: Base
- `.badge-buy`: Warna latar hijau dengan font hijau tebal
- `.badge-sell`: Warna latar merah dengan font merah tebal

### Cards & Panels
- `.kpi-card`: Widget untuk ringkasan harga (klik bisa menavigasi ke trade pair).
- `.panel`: Wrapper berbentuk kotak untuk konten seperti tabel, chart, form. Memiliki sub komponen:
  - `.panel-header` + `.panel-title`
  - `.panel-body` (Bisa ditambah class `.no-padding` untuk tabel rapat).

### Tables
- `.data-table`: Tabel densitas tinggi untuk memuat banyak row pasar kripto, memiliki hover effect.

## 4. Loading States
- Gunakan `<div class="skeleton"></div>` untuk elemen yang asinkron menunggu API respond.
