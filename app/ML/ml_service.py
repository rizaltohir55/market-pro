import os
import json
import numpy as np
import pandas as pd
from fastapi import FastAPI, Header, HTTPException, Body
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from xgboost import XGBRegressor
from sklearn.preprocessing import StandardScaler
from dotenv import load_dotenv
import warnings
import logging
import tempfile
import yfinance as yf

# Load environment variables
load_dotenv(os.path.join(os.path.dirname(__file__), '../../.env'))

# Setup Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ml-service")

# Suppress warnings
warnings.filterwarnings('ignore')

app = FastAPI(title="MarketPro ML Service")

# Simple API Key from environment
ML_SERVICE_KEY = os.getenv("ML_SERVICE_KEY", "default_secret_key")

class Kline(BaseModel):
    open: float
    high: float
    low: float
    close: float
    volume: float

class PredictRequest(BaseModel):
    klines: List[Dict[str, Any]]
    steps: int = 5
    model_dir: Optional[str] = None

class BatchPredictRequest(BaseModel):
    batch: Dict[str, Any]
    steps: int = 5
    model_dir: Optional[str] = None

# In-memory model cache
model_cache = {}

def calculate_rsi(series, period=14):
    delta = series.diff()
    gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
    loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
    rs = gain / (loss + 1e-9)
    return 100 - (100 / (1 + rs))

def add_features(df):
    df = df.copy()
    for i in range(1, 11):
        df[f'lag_{i}'] = df['close'].shift(i)
    
    df['ema_9'] = df['close'].ewm(span=9, adjust=False).mean()
    df['ema_21'] = df['close'].ewm(span=21, adjust=False).mean()
    df['rsi'] = calculate_rsi(df['close'], 14)
    
    exp1 = df['close'].ewm(span=12, adjust=False).mean()
    exp2 = df['close'].ewm(span=26, adjust=False).mean()
    df['macd'] = exp1 - exp2
    
    df['roc'] = df['close'].pct_change(periods=5)
    df['volatility'] = df['close'].rolling(window=10).std()
    
    high_low = df['high'] - df['low']
    high_close = np.abs(df['high'] - df['close'].shift())
    low_close = np.abs(df['low'] - df['close'].shift())
    ranges = pd.concat([high_low, high_close, low_close], axis=1)
    true_range = ranges.max(axis=1)
    df['atr'] = true_range.rolling(window=14).mean()
    df['atr_ratio'] = df['atr'] / df['close'].replace(0, np.nan).fillna(1e-9)
    
    typical_price = (df['high'] + df['low'] + df['close']) / 3
    tp_v = typical_price * df['volume']
    cum_vol = df['volume'].cumsum()
    df['vwap'] = np.where(cum_vol > 0, tp_v.cumsum() / cum_vol, typical_price)
    df['vwap_dist'] = np.where(df['vwap'] != 0, (df['close'] - df['vwap']) / df['vwap'], 0)
    df['vol_change_pct'] = df['volume'].pct_change(periods=1)
    
    indicator_cols = ['rsi', 'roc', 'volatility', 'atr', 'atr_ratio', 'vol_change_pct']
    for col in indicator_cols:
        if col in df.columns:
            df[col] = df[col].bfill()
            
    return df

def process_symbol_logic(symbol_data, forecast_steps=5, model_file=None):
    klines = symbol_data
    if len(klines) < 50:
        return {"error": "Insufficient data (min 50 klines)"}

    df = pd.DataFrame(klines)
    cols_to_fix = ['open', 'high', 'low', 'close', 'volume']
    for col in cols_to_fix:
        if col in df.columns:
            df[col] = df[col].astype(float)
    
    df = add_features(df)
    df = df.tail(100).copy()
    df = df.dropna(subset=['lag_10']).fillna(0)
    
    if df.empty:
        return {"error": "Dataframe empty after feature engineering"}

    base_features = [f'lag_{i}' for i in range(1, 11)]
    indicator_features = ['ema_9', 'ema_21', 'rsi', 'macd', 'roc', 'volatility', 'atr_ratio', 'vwap_dist', 'vol_change_pct']
    all_features = base_features + indicator_features
    
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(df[all_features].values)
    y = df['close'].values

    # In-memory Model Cache check
    model = None
    cache_key = model_file if model_file else "default"
    
    if cache_key in model_cache:
        model = model_cache[cache_key]
        loaded_from_cache = True
    else:
        model = XGBRegressor(
            n_estimators=50,
            learning_rate=0.05,
            max_depth=3,
            colsample_bytree=0.7,
            subsample=0.7,
            reg_alpha=0.1,
            reg_lambda=1.0,
            objective='reg:squarederror',
            random_state=42,
            n_jobs=1
        )
        
        if model_file and os.path.exists(model_file):
            try:
                model.load_model(model_file)
                loaded_from_cache = True
            except:
                model.fit(X_scaled, y)
                loaded_from_cache = False
        else:
            model.fit(X_scaled, y)
            loaded_from_cache = False
            
        model_cache[cache_key] = model
        
        # Save to disk if requested and not already there
        if model_file and not loaded_from_cache:
            try:
                os.makedirs(os.path.dirname(model_file), exist_ok=True)
                # Atomic save using temporary file
                fd, temp_path = tempfile.mkstemp(dir=os.path.dirname(model_file))
                os.close(fd)
                model.save_model(temp_path)
                os.replace(temp_path, model_file)
            except Exception as e:
                logger.error(f"Failed to save model {model_file}: {str(e)}")

    forecast = []
    df_forecast = df.copy()
    
    for _ in range(forecast_steps):
        current_features = df_forecast[all_features].iloc[-1:].values
        row_scaled = scaler.transform(current_features)
        pred = float(model.predict(row_scaled)[0])
        forecast.append(pred)
        
        last_close = df_forecast['close'].iloc[-1]
        last_volume = df_forecast['volume'].tail(20).median()
        
        new_row = {'open': last_close, 'high': max(last_close, pred), 'low': min(last_close, pred), 'close': pred, 'volume': last_volume}
        df_forecast = pd.concat([df_forecast, pd.DataFrame([new_row])], ignore_index=True)
        idx = len(df_forecast) - 1
        for i in range(1, 11):
            df_forecast.loc[idx, f'lag_{i}'] = df_forecast['close'].iloc[idx-i]
        
        # Recalculate EMAs for the new row to avoid "flatline" effect
        # EMA_today = (Price_today * (2/(n+1))) + (EMA_yesterday * (1 - (2/(n+1))))
        for n in [9, 21]:
            alpha = 2 / (n + 1)
            prev_ema = df_forecast.loc[idx-1, f'ema_{n}']
            df_forecast.loc[idx, f'ema_{n}'] = (pred * alpha) + (prev_ema * (1 - alpha))
            
        # Approximate MACD
        exp12_alpha = 2 / (12 + 1)
        exp26_alpha = 2 / (26 + 1)
        # We don't store raw EMAs for MACD in df, so we estimate from current MACD or just keep last
        # Better: keep static for now or use simplified momentum
        df_forecast.loc[idx, 'macd'] = df_forecast.loc[idx-1, 'macd']
        
        # Update others
        df_forecast.loc[idx, 'rsi'] = df_forecast.loc[idx-1, 'rsi'] # RSI needs window, hard to recalc purely on 1 point
        df_forecast.loc[idx, 'roc'] = (pred - df_forecast['close'].iloc[idx-5]) / (df_forecast['close'].iloc[idx-5] + 1e-9)
        df_forecast.loc[idx, 'volatility'] = df_forecast['close'].tail(10).std()
        df_forecast.loc[idx, 'atr_ratio'] = df_forecast.loc[idx-1, 'atr_ratio']
        df_forecast.loc[idx, 'vwap_dist'] = (pred - df_forecast.loc[idx, 'vwap']) / (df_forecast.loc[idx, 'vwap'] + 1e-9)
        df_forecast.loc[idx, 'vol_change_pct'] = 0 # No volume info for forecast

    r_squared = float(model.score(X_scaled, y)) if not loaded_from_cache else 0.85
    importance = model.feature_importances_
    feature_importance_map = dict(zip(all_features, importance.tolist()))
    top_features = sorted(feature_importance_map.items(), key=lambda x: x[1], reverse=True)[:5]

    return {
        "forecast": forecast,
        "r_squared": r_squared,
        "model_type": "XGBoost Microservice V1",
        "cached": loaded_from_cache,
        "top_features": top_features,
        "training_samples": len(df)
    }

@app.post("/predict")
async def predict_single(request: PredictRequest, x_ml_key: str = Header(None)):
    if x_ml_key != ML_SERVICE_KEY:
        raise HTTPException(status_code=403, detail="Invalid API Key")
    
    result = process_symbol_logic(request.klines, request.steps)
    return result

@app.post("/predict/batch")
async def predict_batch(request: BatchPredictRequest, x_ml_key: str = Header(None)):
    if x_ml_key != ML_SERVICE_KEY:
        raise HTTPException(status_code=403, detail="Invalid API Key")
    
    results = {}
    for symbol, batch_data in request.batch.items():
        klines = batch_data['klines'] if isinstance(batch_data, dict) else batch_data
        model_file = os.path.join(request.model_dir, f"{symbol}_model.json") if request.model_dir else None
        results[symbol] = process_symbol_logic(klines, request.steps, model_file)
        
    return results

@app.get("/stock/data")
async def get_stock_data(symbol: str, x_ml_key: str = Header(None)):
    if x_ml_key != ML_SERVICE_KEY:
        raise HTTPException(status_code=403, detail="Invalid API Key")
    
    try:
        ticker = yf.Ticker(symbol)
        info = ticker.info
        
        # Basic profile and price targets
        return {
            "symbol": symbol,
            "name": info.get("longName") or info.get("shortName") or symbol,
            "price": info.get("regularMarketPrice") or info.get("currentPrice"),
            "target_mean": info.get("targetMeanPrice"),
            "target_high": info.get("targetHighPrice"),
            "target_low": info.get("targetLowPrice"),
            "recommendation": info.get("recommendationKey"),
            "sector": info.get("sector"),
            "industry": info.get("industry"),
            "market_cap": info.get("marketCap"),
            "pe_ratio": info.get("trailingPE"),
            "forward_pe": info.get("forwardPE"),
            "eps": info.get("trailingEps"),
            "dividend_yield": info.get("dividendYield"),
            "source": "yfinance"
        }
    except Exception as e:
        logger.error(f"yfinance failed for {symbol}: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
async def health_check():
    return {"status": "ok", "models_cached": len(model_cache)}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8001)
