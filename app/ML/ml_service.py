import os
import json
import logging
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from dotenv import load_dotenv
import yfinance as yf
from ml_core import run_prediction_pipeline
import warnings

# Suppress warnings to keep stdout clean
warnings.filterwarnings('ignore')

# Load environment variables
load_dotenv(os.path.join(os.path.dirname(__file__), '../../.env'))

# Setup Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ml-service")

app = FastAPI(title="MarketPro ML Service")

# Simple API Key from environment
ML_SERVICE_KEY = os.getenv("ML_SERVICE_KEY", "default_secret_key")

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

def process_symbol_logic(symbol_data, forecast_steps=5, model_file=None):
    return run_prediction_pipeline(symbol_data, forecast_steps, model_file, model_cache)

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
        def fetch_yf_data():
            ticker = yf.Ticker(symbol)
            return ticker.info
            
        import asyncio
        info = await asyncio.to_thread(fetch_yf_data)
        
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
