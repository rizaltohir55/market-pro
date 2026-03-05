# MarketPro Dashboard 📈

MarketPro adalah dashboard terminal bergaya profesional untuk cryptocurrency trading, analisis teknikal, dan pemantauan sinyal scalping. Desainnya difokuskan pada data density tinggi, real-time WebSocket connection, dan performa tinggi (Dark Mode native).

## Fitur Utama
1. **Realtime Ticker**: Pembaruan harga instan via Binance Public WebSocket.
2. **Interactive Charting**: Integrasi TradingView Lightweight Charts (kustomisasi warna bullish/bearish).
3. **Multi-Timeframe Analysis**: Algoritma AI prediksi untuk sinyal Scalping (15m, 1h, 4h).
4. **Market Scanner**: Live screener mencari momentum pasar dengan *Top Gainers/Losers*.
5. **Ultra-Fast UI**: Penggunaan state sederhana + CSS Custom Variables (tanpa bundle React yang berat).

## Cara Setup Local

1. Pastikan **PHP >= 8.2**, **Composer**, dan **Node.js** terinstall.
2. Clone repository ini.
3. Install dependensi PHP:
   ```bash
   composer install
   ```
4. Install dependensi Node:
   ```bash
   npm install
   ```
5. Konfigurasi Environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
6. Jalankan Server:
   ```bash
   php artisan serve
   ```
7. Jalankan Asset bundler (Vite):
   ```bash
   npm run dev
   ```

## Folder Structure
- `app/Services`: Logic bisnis API Binance dan TA (Prediction).
- `resources/css/app.css`: Token desain, variabel dark mode, dan kompoen custom.
- `resources/views/*`: View blade per fitur (Dashboard, Trading, Scanner, Analysis, Settings).
