<?php
namespace BD\Tests\Unit;

use BD\Utils\Validation;
use WP_UnitTestCase;

class ValidationTest extends WP_UnitTestCase {
    
    public function test_valid_latitude() {
        $this->assertTrue(Validation::is_valid_latitude(30.2672));
        $this->assertTrue(Validation::is_valid_latitude(-30.2672));
    }
    
    public function test_invalid_latitude() {
        $this->assertFalse(Validation::is_valid_latitude(91));
        $this->assertFalse(Validation::is_valid_latitude(-91));
    }
}
