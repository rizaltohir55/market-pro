<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestSseController extends Controller
{
    public function stream()
    {
        return response()->stream(function () {
            while (true) {
                echo "data: " . time() . "\n\n";
                ob_flush();
                flush();
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

