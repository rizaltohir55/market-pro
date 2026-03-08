import sys
import json
import numpy as np
import pandas as pd
from xgboost import XGBRegressor
from sklearn.preprocessing import StandardScaler

def calculate_rsi(series, period=14):
    delta = series.diff()
    gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
    loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
    rs = gain / loss
    return 100 - (100 / (1 + rs))

def add_features(df):
    df = df.copy()
    
    # Ensure sorted by time if time exists, though here index is sequential
    
    # 1. Lags (Shifted close)
    for i in range(1, 11):
        df[f'lag_{i}'] = df['close'].shift(i)
    
    # 2. Indicators (Manual calculation)
    # EMA with adjust=False starts from the first value (seeded with price)
    df['ema_9'] = df['close'].ewm(span=9, adjust=False).mean()
    df['ema_21'] = df['close'].ewm(span=21, adjust=False).mean()
    df['rsi'] = calculate_rsi(df['close'], 14)
    
    # 3. MACD
    exp1 = df['close'].ewm(span=12, adjust=False).mean()
    exp2 = df['close'].ewm(span=26, adjust=False).mean()
    df['macd'] = exp1 - exp2
    
    # 4. Momentum & Volatility
    df['roc'] = df['close'].pct_change(periods=5)
    df['volatility'] = df['close'].rolling(window=10).std()
    
    # 5. Volatility-Adjusted Features (ATR Ratio)
    high_low = df['high'] - df['low']
    high_close = np.abs(df['high'] - df['close'].shift())
    low_close = np.abs(df['low'] - df['close'].shift())
    ranges = pd.concat([high_low, high_close, low_close], axis=1)
    true_range = ranges.max(axis=1)
    df['atr'] = true_range.rolling(window=14).mean()
    df['atr_ratio'] = df['atr'] / df['close']
    
    # 6. VWAP Distance
    typical_price = (df['high'] + df['low'] + df['close']) / 3
    tp_v = typical_price * df['volume']
    cum_vol = df['volume'].cumsum()
    df['vwap'] = np.where(cum_vol > 0, tp_v.cumsum() / cum_vol, typical_price)
    df['vwap_dist'] = np.where(df['vwap'] != 0, (df['close'] - df['vwap']) / df['vwap'], 0)
    
    # 7. Volume Change
    df['vol_change_pct'] = df['volume'].pct_change(periods=1)
    
    # Fix Data Depletion: Instead of dropping all NaNs immediately, 
    # we backfill the indicator windows to keep more training data.
    # Lags will still have NaNs at the start, which we will handle.
    indicator_cols = ['rsi', 'roc', 'volatility', 'atr', 'atr_ratio', 'vol_change_pct']
    for col in indicator_cols:
        if col in df.columns:
            # fillna(method='bfill') is deprecated in newer pandas, using ffill/bfill directly
            df[col] = df[col].bfill()
            
    return df

def process_symbol(klines, forecast_steps=5):
    if len(klines) < 50:
        return {"error": "Insufficient data (min 50 klines)"}

    # Prepare DataFrame
    df = pd.DataFrame(klines)
    cols_to_fix = ['open', 'high', 'low', 'close', 'volume']
    for col in cols_to_fix:
        if col in df.columns:
            df[col] = df[col].astype(float)
    
    # Apply Feature Engineering
    df = add_features(df)
    
    # Dropping only rows where lag_10 is missing (the longest lag)
    # This preserves (~50 - 10) = 40 rows instead of 24.
    df = df.dropna(subset=['lag_10']).fillna(0)
    
    if df.empty:
        return {"error": "Dataframe empty after feature engineering"}

    # Define Features
    base_features = [f'lag_{i}' for i in range(1, 11)]
    indicator_features = ['ema_9', 'ema_21', 'rsi', 'macd', 'roc', 'volatility', 'atr_ratio', 'vwap_dist', 'vol_change_pct']
    all_features = base_features + indicator_features
    
    X = df[all_features].values
    y = df['close'].values

    # Scaling
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    # Train model
    model = XGBRegressor(
        n_estimators=30,
        learning_rate=0.08,
        max_depth=5,
        colsample_bytree=0.8,
        subsample=0.8,
        objective='reg:squarederror',
        random_state=42,
        n_jobs=1
    )
    model.fit(X_scaled, y)

    # Forecast (Recursive with Dynamic Indicators)
    forecast = []
    df_forecast = df.copy()
    
    for _ in range(forecast_steps):
        # 1. Prepare current features from the VERY LAST row
        current_features = df_forecast[all_features].iloc[-1:].values
        row_scaled = scaler.transform(current_features)
        
        # 2. Predict next close
        pred = float(model.predict(row_scaled)[0])
        forecast.append(pred)
        
        # 3. Append predicted row to df_forecast
        last_close = df_forecast['close'].iloc[-1]
        last_volume = df_forecast['volume'].tail(20).median()
        
        new_row = {
            'open': last_close,
            'high': max(last_close, pred),
            'low': min(last_close, pred),
            'close': pred,
            'volume': last_volume
        }
        
        # Simple concat and recalculate ALL features
        df_forecast = pd.concat([df_forecast, pd.DataFrame([new_row])], ignore_index=True)
        df_forecast = add_features(df_forecast)

    # Accuracy Metric (R2)
    r_squared = float(model.score(X_scaled, y))
    
    # Feature Importance
    importance = model.feature_importances_
    feature_importance_map = dict(zip(all_features, importance.tolist()))
    top_features = sorted(feature_importance_map.items(), key=lambda x: x[1], reverse=True)[:5]

    return {
        "forecast": forecast,
        "r_squared": r_squared,
        "model_type": "XGBoost (Ultra V6 - Dynamic)",
        "top_features": top_features,
        "features_used": all_features,
        "training_samples": len(df)
    }

def main():
    try:
        # Read input from stdin
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"error": "No input data"}))
            return

        data = json.loads(input_data)
        
        # Handle Batch Input
        if 'batch' in data:
            batch_results = {}
            steps = data.get('steps', 5)
            for symbol, klines in data['batch'].items():
                batch_results[symbol] = process_symbol(klines, steps)
            print(json.dumps(batch_results))
            return

        # Handle Single Input (Backward Compatibility)
        klines = data.get('klines', [])
        forecast_steps = data.get('steps', 5)
        
        result = process_symbol(klines, forecast_steps)
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
