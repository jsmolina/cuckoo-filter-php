<?php
declare(strict_types = 1);

require(dirname(__FILE__) . '/../src/CuckooFilter.php');

use PHPUnit\Framework\TestCase;
use Cuckoo\CuckooFilter;


class CuckooFilterTest extends TestCase
{

    public function testCuckooFilterContains()
    {
        $cuckoo = $this->_fixture();

        $this->assertTrue($cuckoo->contain(2));
        $this->assertTrue($cuckoo->contain(200));
    }

    public function testCuckooFilterDelete()
    {
        $cuckoo = $this->_fixture();

        $cuckoo->delete(2);
        $this->assertFalse($cuckoo->contain(2));
        $this->assertTrue($cuckoo->contain(200));
    }

    protected function _fixture($size = 100)
    {
        $cuckoo = new CuckooFilter(32, $size);
        $cuckoo->add(2);
        $cuckoo->add(200);
        return $cuckoo;
    }
}
