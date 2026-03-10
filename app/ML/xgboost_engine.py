import sys
import json
import os
import warnings
from ml_core import run_prediction_pipeline

# Suppress warnings to keep stdout clean for JSON
warnings.filterwarnings('ignore')
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

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
            model_dir = data.get('model_dir')
            
            for symbol, batch_data in data['batch'].items():
                klines_to_process = batch_data['klines'] if isinstance(batch_data, dict) else batch_data
                model_file = os.path.join(model_dir, f"{symbol}_model.json") if model_dir else None
                batch_results[symbol] = run_prediction_pipeline(klines_to_process, steps, model_file)
            print(json.dumps(batch_results))
            return

        # Handle Single Input
        klines = data.get('klines', [])
        forecast_steps = data.get('steps', 5)
        model_dir = data.get('model_dir')
        model_file = os.path.join(model_dir, "single_model.json") if model_dir else None
        
        result = run_prediction_pipeline(klines, forecast_steps, model_file)
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
