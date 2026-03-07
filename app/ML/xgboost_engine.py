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

def main():
    try:
        # Read input from stdin
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"error": "No input data"}))
            return

        data = json.loads(input_data)
        klines = data.get('klines', [])
        forecast_steps = data.get('steps', 5)

        if len(klines) < 50:
            print(json.dumps({"error": "Insufficient data (min 50 klines)"}))
            return

        # Prepare DataFrame
        df = pd.DataFrame(klines)
        cols_to_fix = ['open', 'high', 'low', 'close', 'volume']
        for col in cols_to_fix:
            if col in df.columns:
                df[col] = df[col].astype(float)
        
        # --- Advanced Feature Engineering ---
        # 1. Lags
        for i in range(1, 11):
            df[f'lag_{i}'] = df['close'].shift(i)
        
        # 2. Indicators
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
        df['vwap'] = tp_v.cumsum() / df['volume'].cumsum()
        df['vwap_dist'] = (df['close'] - df['vwap']) / df['vwap']
        
        # 7. Volume Change
        df['vol_change_pct'] = df['volume'].pct_change(periods=1)
        
        # Drop NaNs
        df = df.dropna()
        
        if df.empty:
            print(json.dumps({"error": "Dataframe empty after feature engineering"}))
            return

        # Define Features
        base_features = [f'lag_{i}' for i in range(1, 11)]
        indicator_features = ['ema_9', 'ema_21', 'rsi', 'macd', 'roc', 'volatility', 'atr_ratio', 'vwap_dist', 'vol_change_pct']
        all_features = base_features + indicator_features
        
        X = df[all_features].values
        y = df['close'].values

        # Scaling
        scaler = StandardScaler()
        X_scaled = scaler.fit_transform(X)

        # Train model with tuned parameters
        model = XGBRegressor(
            n_estimators=30, # Slightly more estimators for added features
            learning_rate=0.08,
            max_depth=5,     # Increased depth for complex interactions
            colsample_bytree=0.8,
            subsample=0.8,
            objective='reg:squarederror',
            random_state=42,
            n_jobs=1
        )
        model.fit(X_scaled, y)

        # Forecast (Recursive)
        last_row = X[-1].copy()
        forecast = []
        
        for _ in range(forecast_steps):
            # Scale the input
            row_scaled = scaler.transform(last_row.reshape(1, -1))
            pred = float(model.predict(row_scaled)[0])
            forecast.append(pred)
            
            # Update lags for next step
            new_row = last_row.copy()
            new_row[1:10] = last_row[0:9] # shift lags
            new_row[0] = pred # Newest lag
            # Note: Indicators remain static in this simplified recursive forecast
            last_row = new_row

        # Accuracy Metric (R2)
        r_squared = float(model.score(X_scaled, y))
        
        # Feature Importance
        importance = model.feature_importances_
        feature_importance_map = dict(zip(all_features, importance.tolist()))
        top_features = sorted(feature_importance_map.items(), key=lambda x: x[1], reverse=True)[:5]

        print(json.dumps({
            "forecast": forecast,
            "r_squared": r_squared,
            "model_type": "XGBoost (Ultra V4)",
            "top_features": top_features,
            "features_used": all_features
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
