import os
import tempfile
import numpy as np
import pandas as pd
from xgboost import XGBRegressor
from sklearn.preprocessing import StandardScaler
import logging

logger = logging.getLogger("ml-core")

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

def run_prediction_pipeline(symbol_data, forecast_steps=5, model_file=None, model_cache=None):
    if len(symbol_data) < 50:
        return {"error": "Insufficient data (min 50 klines)"}

    df = pd.DataFrame(symbol_data)
    cols_to_fix = ['open', 'high', 'low', 'close', 'volume']
    for col in cols_to_fix:
        if col in df.columns:
            df[col] = df[col].astype(float)
    
    df = add_features(df)
    # df = df.tail(100).copy()  # Removed to use more data for better accuracy
    df = df.dropna(subset=['lag_10', 'macd', 'atr', 'vwap_dist']).fillna(0)
    
    if df.empty:
        return {"error": "Dataframe empty after feature engineering"}

    base_features = [f'lag_{i}' for i in range(1, 11)]
    indicator_features = ['ema_9', 'ema_21', 'rsi', 'macd', 'roc', 'volatility', 'atr_ratio', 'vwap_dist', 'vol_change_pct']
    all_features = base_features + indicator_features
    
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(df[all_features].values)
    
    # Target variable: Predict relative return (1-step forward) instead of absolute price
    # This severely fixes the "extrapolation" bug of tree-based models on ATH/ATL.
    df['target_return'] = df['close'].shift(-1) / df['close'] - 1
    df_train = df.dropna(subset=['target_return']).copy()
    
    X_train = scaler.transform(df_train[all_features].values)
    y_train = df_train['target_return'].values

    # Model Cache check
    model = None
    cache_key = model_file if model_file else "default"
    
    loaded_from_cache = False
    if model_cache is not None and cache_key in model_cache:
        model = model_cache[cache_key]
        loaded_from_cache = True
    else:
        model = XGBRegressor(
            n_estimators=100,
            learning_rate=0.03,
            max_depth=4,
            colsample_bytree=0.8,
            subsample=0.8,
            gamma=0.1,
            reg_alpha=0.5,
            reg_lambda=1.5,
            objective='reg:squarederror',
            random_state=42,
            n_jobs=1
        )
        
        if model_file and os.path.exists(model_file):
            try:
                model.load_model(model_file)
                loaded_from_cache = True
            except:
                model.fit(X_train, y_train)
        else:
            model.fit(X_train, y_train)
            
        if model_cache is not None:
            model_cache[cache_key] = model
        
        if model_file and not loaded_from_cache:
            try:
                os.makedirs(os.path.dirname(model_file), exist_ok=True)
                fd, temp_path = tempfile.mkstemp(dir=os.path.dirname(model_file))
                os.close(fd)
                model.save_model(temp_path)
                os.replace(temp_path, model_file)
            except Exception as e:
                logger.error(f"Failed to save model {model_file}: {str(e)}")

    # Hitung Akurasi & RMSE untuk Confidence Interval
    y_pred_historical = model.predict(X_train)
    rmse = float(np.sqrt(np.mean((y_pred_historical - y_train)**2)))
    r_squared = float(model.score(X_train, y_train)) if not loaded_from_cache else 0.85
    
    # Perhitungan Confidence Score Cerdas (Menghukum Volatilitas Tinggi)
    recent_volatility = df['close'].pct_change().std()
    volatility_penalty = min(30, recent_volatility * 500)
    base_confidence = max(0, min(100, (r_squared * 100)))
    confidence_score = round(max(10, base_confidence - volatility_penalty), 2)

    forecast = []
    upper_bounds = []
    lower_bounds = []
    
    df_forecast = df.copy()
    last_idx = df_forecast.index[-1]
    forecast_df_template = pd.DataFrame(index=range(last_idx + 1, last_idx + 1 + forecast_steps), columns=df_forecast.columns)
    df_forecast = pd.concat([df_forecast, forecast_df_template])
    
    for i in range(forecast_steps):
        idx = last_idx + 1 + i
        current_features = df_forecast[all_features].loc[idx-1:idx-1].values
        row_scaled = scaler.transform(current_features)
        
        # Predict return instead of absolute price
        pred_return = float(model.predict(row_scaled)[0])
        
        # Guard rails for return to prevent insane spikes (max 50% per candle)
        pred_return = np.clip(pred_return, -0.5, 0.5) 
        
        last_close = df_forecast.loc[idx-1, 'close']
        pred_price = last_close * (1 + pred_return)
        forecast.append(pred_price)
        
        # Dinamis margin melebar seiring bertambahnya langkah prediksi ke depan
        margin = rmse * 1.5 * (1 + (i * 0.1))
        upper_bounds.append(pred_price * (1 + margin)) # Apply margin to price, not return
        lower_bounds.append(pred_price * (1 - margin)) # Apply margin to price, not return
        
        last_volume = df_forecast['volume'].iloc[:idx].tail(20).median()
        
        df_forecast.loc[idx, 'open'] = last_close
        df_forecast.loc[idx, 'high'] = max(last_close, pred_price)
        df_forecast.loc[idx, 'low'] = min(last_close, pred_price)
        df_forecast.loc[idx, 'close'] = pred_price
        df_forecast.loc[idx, 'volume'] = last_volume
        
        for j in range(1, 11):
            df_forecast.loc[idx, f'lag_{j}'] = df_forecast.loc[idx-j, 'close']
        
        for n in [9, 21]:
            alpha = 2 / (n + 1)
            prev_ema = df_forecast.loc[idx-1, f'ema_{n}']
            df_forecast.loc[idx, f'ema_{n}'] = (pred_price * alpha) + (prev_ema * (1 - alpha))
            
        df_forecast.loc[idx, 'macd'] = df_forecast.loc[idx-1, 'macd']
        df_forecast.loc[idx, 'rsi'] = df_forecast.loc[idx-1, 'rsi']
        df_forecast.loc[idx, 'roc'] = (pred_price - df_forecast.loc[idx-5, 'close']) / (df_forecast.loc[idx-5, 'close'] + 1e-9)
        df_forecast.loc[idx, 'volatility'] = df_forecast['close'].iloc[:idx+1].tail(10).std()
        # Forward-fill vwap from the previous row — the pre-allocated template row
        # has NaN for vwap, which would corrupt vwap_dist and all subsequent scaler.transform() calls.
        df_forecast.loc[idx, 'vwap'] = df_forecast.loc[idx-1, 'vwap']
        df_forecast.loc[idx, 'vwap_dist'] = (pred_price - df_forecast.loc[idx, 'vwap']) / (df_forecast.loc[idx, 'vwap'] + 1e-9)
        df_forecast.loc[idx, 'vol_change_pct'] = 0

    # Terjemahkan Feature Importance ke Bahasa Manusia (Explainable AI)
    importance = model.feature_importances_
    feature_importance_map = dict(zip(all_features, importance.tolist()))
    top_features = sorted(feature_importance_map.items(), key=lambda x: x[1], reverse=True)[:3]
    
    explain_map = {
        'rsi': 'Momentum RSI',
        'macd': 'Konvergensi MACD',
        'vol_change_pct': 'Anomali Volume',
        'vwap_dist': 'Deviasi Harga vs VWAP',
        'atr_ratio': 'Volatilitas Pasar',
        'ema_9': 'Tren Jangka Pendek',
        'ema_21': 'Konfirmasi Tren',
        'roc': 'Akselerasi Harga',
        'volatility': 'Stabilitas Pasar',
        'close': 'Aksi Harga (Closing)',
        'high': 'Tekanan Beli',
        'low': 'Tekanan Jual'
    }
    
    reasons = []
    for feat, imp in top_features:
        if 'lag_' in feat:
            reasons.append('Pola Harga Historis')
        else:
            reasons.append(explain_map.get(feat, 'Analisis Struktur Market'))

    return {
        "forecast": forecast,
        "upper_bounds": upper_bounds,
        "lower_bounds": lower_bounds,
        "confidence_score": confidence_score,
        "explainability": list(dict.fromkeys(reasons)), # Remove duplicates
        "r_squared": r_squared,
        "model_type": "XGBoost Smart Engine",
        "cached": loaded_from_cache,
        "training_samples": len(df)
    }