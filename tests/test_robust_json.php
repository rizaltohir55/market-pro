<?php

// Mocking Log for standalone test
class Log {
    public static function error($msg) { echo "LOG ERROR: $msg\n"; }
}

class TestMLService {
    public function testExtraction($input) {
        return $this->extractJson($input);
    }

    private function extractJson(string $input): ?array
    {
        $input = trim($input);
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $firstBrace = strpos($input, '{');
        $firstBracket = strpos($input, '[');
        $startPos = false;
        if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
            $startPos = $firstBrace;
            $endChar = '}';
        } elseif ($firstBracket !== false) {
            $startPos = $firstBracket;
            $endChar = ']';
        }

        if ($startPos !== false) {
            $lastPos = strrpos($input, $endChar);
            if ($lastPos !== false && $lastPos > $startPos) {
                $jsonString = substr($input, $startPos, $lastPos - $startPos + 1);
                $decoded = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }
        return null;
    }
}

$service = new TestMLService();

$cases = [
    "Pure JSON" => '{"status":"ok"}',
    "With Warning" => "DeprecationWarning: pandas is old\n{\"status\":\"ok\"}",
    "With Multiple Lines" => "Something happened\nWarning: trace\n\n{\"data\":[1,2,3]}\nDone.",
    "Array JSON" => "Noise [1, 2, 3] More noise",
];

foreach ($cases as $name => $input) {
    echo "Testing $name:\n";
    $res = $service->testExtraction($input);
    if ($res) {
        echo "SUCCESS: " . json_encode($res) . "\n";
    } else {
        echo "FAILED\n";
    }
    echo "------------------\n";
}
