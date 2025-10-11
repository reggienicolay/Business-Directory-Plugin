<?php
namespace BD\Tests\Unit;

use BD\DB\LocationsTable;
use WP_UnitTestCase;

class LocationsTableTest extends WP_UnitTestCase {
    
    public function test_insert_with_valid_data() {
        $data = [
            'business_id' => 123,
            'lat' => 30.2672,
            'lng' => -97.7431,
            'address' => '123 Main St',
            'city' => 'Austin',
        ];
        
        $result = LocationsTable::insert($data);
        $this->assertTrue($result);
    }
    
    public function test_insert_with_missing_fields() {
        $data = ['business_id' => 456];
        
        $result = LocationsTable::insert($data);
        $this->assertWPError($result);
    }
}
