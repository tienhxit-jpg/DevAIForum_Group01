<?php

declare(strict_types=1);

final class TestCase
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (Throwable $exception) {
            $this->failed++;
            echo "[FAIL] {$name}: {$exception->getMessage()}\n";
        }
    }

    public function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                'Expected %s, got %s',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public function assertTrue(bool $condition, string $message = 'Expected true'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertFalse(bool $condition, string $message = 'Expected false'): void
    {
        if ($condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("Missing expected text: {$needle}");
        }
    }

    public function assertNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException("Unexpected text found: {$needle}");
        }
    }

    public function assertThrows(callable $callback, string $exceptionClass): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $exceptionClass) {
                return;
            }
            throw new RuntimeException('Expected ' . $exceptionClass . ', got ' . $exception::class);
        }
        throw new RuntimeException('Expected exception ' . $exceptionClass . ' was not thrown');
    }

    public function finish(): never
    {
        echo "\n{$this->passed} passed, {$this->failed} failed\n";
        exit($this->failed === 0 ? 0 : 1);
    }
}
