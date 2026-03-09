@echo off
echo Starting MarketPro Real-time Services...

:: Start Laravel Reverb
start "Reverb" cmd /k "php artisan reverb:start"

:: Start Broadcast Daemon
start "Broadcast" cmd /k "php artisan app:broadcast-market"

:: Start ML Microservice
start "ML Service" cmd /k "python -m uvicorn app.ML.ml_service:app --host 127.0.0.1 --port 8001"

echo.
echo All services are starting in separate windows.
echo Keep them open to maintain real-time updates.
pause
