<?php
declare(strict_types=1);

require(dirname(__FILE__) . '/../src/Table32Bits.php');

use PHPUnit\Framework\TestCase;
use Cuckoo\Table32Bits;


define("KICKOFF", true);
define("NO_KICKOFF", false);


/**
 * Class Table32BitsTest
 * in-memory implementation of cuckoo filters
 */
class Table32BitsTest extends TestCase
{
    public function testInsertedItemsAreFound() {
        $fixtureFlags = array_fill(0, 10, 0);

        $table = new Table32Bits(10);

        // fixture: insert 4 tags
        for ($i = 0; $i < count($fixtureFlags); $i++) {
            $fixtureFlags[$i] = rand(1, 10);
            $table->insertTagToBucket($i, $fixtureFlags[$i], KICKOFF);
        }

        // now validate if all tags are there, play a bit with forEach
        foreach($fixtureFlags as $i=>$value) {
            $this->assertNotEquals($table->findTagInBucket($i, $value), -1);
        }
    }
}
