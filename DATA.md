# Data Architecture & API Connections

## 1. Sumber Data Utama
Data seluruhnya bersumber dari public API **Binance**.
- **REST API Endpoint**: `https://api.binance.com/api/v3/`
- **WebSocket Endpoint**: `wss://stream.binance.com:9443/ws/`

## 2. Koneksi Frontend (WebSocket)
Untuk menghemat sumber daya server Laravel, data berfrekuensi tinggi (harga instan, klines chart) diambil langsung oleh browser melalui WebSocket.

```javascript
// Contoh koneksi kline
const ws = new WebSocket(`wss://stream.binance.com:9443/ws/btcusdt@kline_15m`);
ws.onmessage = (event) => {
    const k = JSON.parse(event.data).k;
    // Update chart
};
```

## 3. Skema Data (Entities)

### Pair Ticker (Market Overview)
Diambil dari REST API `/api/v3/ticker/24hr` (via Controller caching).
- `symbol` (String, "BTCUSDT")
- `lastPrice` (Float)
- `priceChangePercent` (Float, % 24 jam)
- `quoteVolume` (Float, total USDT)
- `highPrice` / `lowPrice`

### Candlestick / Kline
- `time` (Integer ms UNIX)
- `open` (Float)
- `high` (Float)
- `low` (Float)
- `close` (Float)
- `volume` (Float)

### Scanner / Prediction Object
Dimock internal melalui algoritma `PredictionService`.
- `signal` (Enum: "BUY", "SELL", "NEUTRAL")
- `confidence` (Integer 0-100)
- `indicators`: Array of `{ name, value, signal }`
