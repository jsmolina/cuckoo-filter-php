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

    public function testCuckooFilterKickout()
    {
        $cuckoo = new CuckooFilter(32, 300);

        $cuckoo->add(2);
        $cuckoo->add(200);
        $cuckoo->add(6);
        $cuckoo->add(7);

        $this->assertTrue($cuckoo->contain(200));
        $this->assertFalse($cuckoo->contain(900));
        $cuckoo->add(8);
        $cuckoo->add(700);
        $this->assertTrue($cuckoo->contain(8));
        $this->assertTrue($cuckoo->contain(7));
        $this->assertTrue($cuckoo->contain(700));

        $this->assertEquals($cuckoo->loadFactor() * 100, 1.171875);

        $cuckoo->add(9);
        $this->assertEquals($cuckoo->loadFactor() * 100, 1.3671875);

    }

    public function testCuckooFilterForNetwork()
    {

        $cuckoo = new CuckooFilter(32, 1000);
        $ips = array(
            $this->_getIpIntRepr("192.168.1.1"),
            $this->_getIpIntRepr("192.168.1.2"),
            $this->_getIpIntRepr("192.168.1.6"));

        // e.g. these IPs tried a brute force attack, and thus are banned now
        foreach ($ips as $ip) {
            $cuckoo->add($ip);
        }
        foreach ($ips as $ip) {
            $ipDotted = $this->_getIpFromInt($ip);
            $this->assertTrue($cuckoo->contain($ip), "expected $ipDotted found");
        }
    }

    /**
     * Helper to obtain a numeric representation from an IP
     *
     * @param $ipDotted
     * @return mixed
     */
    protected function _getIpIntRepr($ipDotted)
    {
        $ipArr = explode(".", $ipDotted);
        $ip = $ipArr[0] * 0x1000000
            + $ipArr[1] * 0x10000
            + $ipArr[2] * 0x100
            + $ipArr[3];
        return $ip;
    }

    /**
     * Helper to obtain an IP representation from a numeric storage
     *
     * @param $ipVal
     * @return string
     */
    protected function _getIpFromInt($ipVal)
    {
        $ipArr = array(0 =>floor($ipVal / 0x1000000) );
        $ipVint   = $ipVal - ($ipArr[0] * 0x1000000); // for clarity
        $ipArr[1] = ($ipVint & 0xFF0000)  >> 16;
        $ipArr[2] = ($ipVint & 0xFF00  )  >> 8;
        $ipArr[3] =  $ipVint & 0xFF;
        $ipDotted = implode('.', $ipArr);
        return $ipDotted;
    }

    protected function _fixture($size = 100)
    {
        $cuckoo = new CuckooFilter(32, $size);
        $cuckoo->add(2);
        $cuckoo->add(200);
        return $cuckoo;
    }
}
