<?php
/**
 * Framework de Testing Simple para Sequoia Speed
 */

class SimpleTest {
    private $tests = 0;
    private $passed = 0;
    private $failed = 0;
    private $results = [];
    
    public function assertEquals($expected, $actual, $message = "") {
        $this->tests++;
        if ($expected === $actual) {
            $this->passed++;
            $this->results[] = ["type" => "pass", "message" => $message];
            echo "✓ $message\n";
        } else {
            $this->failed++;
            $this->results[] = ["type" => "fail", "message" => $message, "expected" => $expected, "actual" => $actual];
            echo "✗ $message - Expected: $expected, Got: $actual\n";
        }
    }
    
    public function assertTrue($condition, $message = "") {
        $this->assertEquals(true, $condition, $message);
    }
    
    public function assertNotNull($value, $message = "") {
        $this->tests++;
        if ($value !== null) {
            $this->passed++;
            $this->results[] = ["type" => "pass", "message" => $message];
            echo "✓ $message\n";
        } else {
            $this->failed++;
            $this->results[] = ["type" => "fail", "message" => $message];
            echo "✗ $message - Value was null\n";
        }
    }
    
    public function getResults() {
        return [
            "total" => $this->tests,
            "passed" => $this->passed,
            "failed" => $this->failed,
            "success_rate" => $this->tests > 0 ? round(($this->passed / $this->tests) * 100, 2) : 0,
            "results" => $this->results
        ];
    }
    
    public function summary() {
        echo "\n📊 Testing Summary:\n";
        echo "Tests: $this->tests | Passed: $this->passed | Failed: $this->failed\n";
        echo "Success Rate: " . ($this->tests > 0 ? round(($this->passed / $this->tests) * 100, 2) : 0) . "%\n\n";
    }
}
