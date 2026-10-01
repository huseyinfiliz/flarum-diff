<?php

namespace HuseyinFiliz\Diff\Tests\Unit;

use HuseyinFiliz\Diff\Jobs\ArchiveDiffs;
use PHPUnit\Framework\TestCase;

class ArchiveDiffsTest extends TestCase
{
    public function test_sanitize_float_with_valid_numbers()
    {
        $this->assertEquals(15.0, ArchiveDiffs::sanitizeFloat(15));
        $this->assertEquals(0.4, ArchiveDiffs::sanitizeFloat(0.4));
        $this->assertEquals(12.34, ArchiveDiffs::sanitizeFloat(12.34));
    }

    public function test_sanitize_float_with_negative_numbers()
    {
        $this->assertEquals(-5.5, ArchiveDiffs::sanitizeFloat(-5.5));
    }

    public function test_sanitize_float_with_zero()
    {
        $this->assertEquals(0.0, ArchiveDiffs::sanitizeFloat(0));
    }
}
