import os
import json
import logging
import warnings
import asyncio

# Suppress warnings to keep logs clean
warnings.filterwarnings('ignore')
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

from fastapi import FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import List, Dict, Any, Optional, Union
from dotenv import load_dotenv
import yfinance as yf
from cachetools import TTLCache

import sys
import os
# Ensure app/ML is in the path so we can import local modules when running from project root
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from ml_core import run_prediction_pipeline

load_dotenv()

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger("ml-service")

app = FastAPI(title="MarketPro ML Service", version="2.0.0")

ML_SERVICE_KEY = os.getenv("ML_SERVICE_KEY", "default_secret_key")

class PredictRequest(BaseModel):
    klines: List[Dict[str, Any]]
    steps: int = 5
    model_dir: Optional[str] = None

class BatchPredictRequest(BaseModel):
    batch: Dict[str, Union[Dict[str, Any], List[Dict[str, Any]]]]
    steps: int = 5
    model_dir: Optional[str] = None

model_cache = {}
stock_data_cache = TTLCache(maxsize=500, ttl=900)

async def verify_api_key(x_ml_key: str = Header(None)):
    if x_ml_key != ML_SERVICE_KEY:
        logger.warning("Unauthorized access attempt rejected.")
        raise HTTPException(status_code=403, detail="Invalid or missing API Key")
    return x_ml_key

def process_symbol_logic(symbol_data: List[Dict[str, Any]], forecast_steps: int = 5, model_file: Optional[str] = None):
    try:
        return run_prediction_pipeline(symbol_data, forecast_steps, model_file, model_cache)
    except Exception as e:
        logger.error(f"Pipeline error: {str(e)}")
        return {"error": str(e)}

@app.post("/predict")
async def predict_single(request: PredictRequest, key: str = Header(None)):
    await verify_api_key(key)
    result = await asyncio.to_thread(process_symbol_logic, request.klines, request.steps, request.model_dir)
    return result

@app.post("/predict/batch")
async def predict_batch(request: BatchPredictRequest, key: str = Header(None)):
    await verify_api_key(key)
    results = {}
    
    for symbol, batch_data in request.batch.items():
        klines = batch_data['klines'] if isinstance(batch_data, dict) else batch_data
        model_file = os.path.join(request.model_dir, f"{symbol}_model.json") if request.model_dir else None
        
        # Proses di thread terpisah agar event loop tidak terblokir
        results[symbol] = await asyncio.to_thread(process_symbol_logic, klines, request.steps, model_file)
        
    return results

@app.get("/stock/data")
async def get_stock_data(symbol: str, key: str = Header(None)):
    await verify_api_key(key)
    symbol = symbol.upper()
    
    if symbol in stock_data_cache:
        return stock_data_cache[symbol]
        
    try:
        def fetch_yf_data():
            ticker = yf.Ticker(symbol)
            return ticker.info
            
        info = await asyncio.to_thread(fetch_yf_data)
        
        result = {
            "symbol": symbol,
            "name": info.get("longName", info.get("shortName", symbol)),
            "price": info.get("regularMarketPrice", info.get("currentPrice")),
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
        
        stock_data_cache[symbol] = result
        return result
        
    except Exception as e:
        logger.error(f"yfinance failed for {symbol}: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to fetch stock data: {str(e)}")

@app.get("/health")
async def health_check():
    return {"status": "ok", "models_cached": len(model_cache), "service": "MarketPro ML"}

@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.error(f"Unhandled exception: {str(exc)}")
    return JSONResponse(status_code=500, content={"error": "Internal ML Service Error"})

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("ml_service:app", host="127.0.0.1", port=8001, reload=False, workers=1)