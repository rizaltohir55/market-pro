import sys
import json
import os
import warnings

# Isolasi stdout ke stderr selama proses import dan inisialisasi
# Ini sangat krusial agar warning dari C++/TensorFlow/XGBoost tidak merusak parsing JSON di PHP
original_stdout = sys.stdout
sys.stdout = sys.stderr

warnings.filterwarnings('ignore')
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
os.environ['KMP_WARNINGS'] = 'off'

try:
    import sys
    import os
    # Ensure app/ML is in the path so we can import local modules when running from project root
    sys.path.append(os.path.dirname(os.path.abspath(__file__)))

    from ml_core import run_prediction_pipeline
except Exception as e:
    sys.stdout = original_stdout
    print(json.dumps({"error": f"Failed to import ml_core: {str(e)}"}))
    sys.exit(1)

def main():
    try:
        # Baca input dari stdin
        input_data = sys.stdin.read()
        if not input_data or not input_data.strip():
            sys.stdout = original_stdout
            print(json.dumps({"error": "No input data provided"}))
            return

        data = json.loads(input_data)
        
        # Mode Batch
        if 'batch' in data:
            batch_results = {}
            steps = data.get('steps', 5)
            model_dir = data.get('model_dir')
            
            for symbol, batch_data in data['batch'].items():
                klines_to_process = batch_data['klines'] if isinstance(batch_data, dict) else batch_data
                model_file = os.path.join(model_dir, f"{symbol}_model.json") if model_dir else None
                try:
                    batch_results[symbol] = run_prediction_pipeline(klines_to_process, steps, model_file)
                except Exception as e:
                    batch_results[symbol] = {"error": f"Prediction failed for {symbol}: {str(e)}"}
            
            sys.stdout = original_stdout
            print(json.dumps(batch_results))
            return

        # Mode Single
        klines = data.get('klines', [])
        forecast_steps = data.get('steps', 5)
        model_dir = data.get('model_dir')
        model_file = os.path.join(model_dir, "single_model.json") if model_dir else None
        
        result = run_prediction_pipeline(klines, forecast_steps, model_file)
        
        # Kembalikan stdout untuk output JSON
        sys.stdout = original_stdout
        print(json.dumps(result))

    except json.JSONDecodeError as e:
        sys.stdout = original_stdout
        print(json.dumps({"error": f"Invalid JSON input: {str(e)}"}))
    except Exception as e:
        sys.stdout = original_stdout
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()