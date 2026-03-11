import os
import json
import logging
import warnings

# Suppress warnings to keep stdout clean before importing heavy ML/Data libraries
warnings.filterwarnings('ignore')

from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from dotenv import load_dotenv
import yfinance as yf
from ml_core import run_prediction_pipeline
from cachetools import TTLCache
import asyncio

# Load environment variables robustly (will try to find .env in working dir or parent dirs)
load_dotenv()

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

# Caching for yfinance to prevent rate limits (max 500 items, expires in 15 mins)
stock_data_cache = TTLCache(maxsize=500, ttl=900)

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
    
    # Check cache first to avoid Yahoo Finance rate limits
    if symbol in stock_data_cache:
        logger.info(f"Serving yfinance data for {symbol} from cache")
        return stock_data_cache[symbol]
        
    try:
        def fetch_yf_data():
            ticker = yf.Ticker(symbol)
            return ticker.info
            
        info = await asyncio.to_thread(fetch_yf_data)
        
        result = {
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
        
        # Save to cache
        stock_data_cache[symbol] = result
        return result
        
    except Exception as e:
        logger.error(f"yfinance failed for {symbol}: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
async def health_check():
    return {"status": "ok", "models_cached": len(model_cache)}

if __name__ == "__main__":
    import uvicorn
    # WARNING: This block is meant for local development only.
    # In production, run using Gunicorn: 
    # gunicorn ml_service:app -w 4 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8001
    
    # The default reload=False is safer for Windows to avoid multiprocess bugs.
    uvicorn.run("ml_service:app", host="127.0.0.1", port=8001, reload=False)
