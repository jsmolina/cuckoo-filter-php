<?php
declare(strict_types = 1);
namespace Cuckoo;

require('helpers.php');
require('exceptions.php');

define('NOT_USED_VICTIM', false);
define('USED_VICTIM', false);
define('KMAXCUCKOOCOUNT', 500);

/**
 * Class CuckooFilter
 *
 * @see https://www.cs.cmu.edu/~dga/papers/cuckoo-conext2014.pdf
 */
class CuckooFilter
{
    protected $_bitsPerItem;
    protected $_maxNumKeys;
    protected $_table;
    protected $_numItems;

    /**
     * CuckooFilter constructor.
     *
     * @param int $_bitsPerItem
     * @param int $_maxNumKeys
     */
    public function __construct(int $_bitsPerItem, int $_maxNumKeys)
    {
        $this->_bitsPerItem = $_bitsPerItem;
        $this->_maxNumKeys = $_maxNumKeys;

        $assoc = 4;
        $numBuckets = $this->_upperpower2($this->_maxNumKeys / $assoc);
        $frac = $this->_maxNumKeys / $numBuckets / $assoc;
        if ($frac > 0.96) {
            $numBuckets = $numBuckets << 1;
        }

        $this->_table = new Table32Bits($numBuckets);
        $this->_victim = new VictimCache();
        $this->_numItems = 0;
    }

    /**
     * Return the smallest power of 2 greater than or equal to the specified number
     *
     * @param float $x
     * @return int
     */
    protected function _upperpower2(float $x): int
    {
        $x = (int)$x;
        $x--;
        $x |= $x >> 1;
        $x |= $x >> 2;
        $x |= $x >> 4;
        $x |= $x >> 8;
        $x |= $x >> 16;
        $x |= $x >> 32;
        $x += 1;
        return $x;
    }

    /**
     * Djb2 algorythm implementation
     *
     * @param $value
     * @return int
     */
    protected function _hashDjb2(int $value): int
    {

        $value = (string)$value;
        $result = 5381;
        foreach (str_split($value) as $ch) {
            $result = $result * 33 + ord($ch);
        }
        return $result & 0xffffffff;
    }

    protected function _taghash(int $hv): int
    {
        $tag = $hv & ((1 << $this->_bitsPerItem) - 1);
        // $tag += ($tag == 0);
        return $tag;
    }

    /**
     * method to obtain the initial index and the tag/fingerprint
     *
     * @param int $item the item to get the corresponding index and tag
     * @return array of (index, tag)
     */
    protected function _generateIndexTagHash(int $item): array
    {
        $hashedKey = $this->_hashDjb2($item);
        return array(($hashedKey >> 8) % count($this->_table), $hashedKey & 0xff);
    }

    /**
     *
     * method to obtain the alternate index for an item, based on
     * the tag instead of the original value.
     * Note that the paper describes this operation as: index XOR hash(tag)
     * however on the C++ implementation it's optimized by hardcoding the
     * MurmurHash2 constant as a means of having a very quick hash-like function.
     *
     * @param int $index
     * @param int $tag
     * @return int
     */
    protected function _altIndex(int $index, int $tag): int
    {
        return ($index ^ ($tag * 0x5bd1e995)) % count($this->_table);
    }

    public function add(int $item): bool
    {
        if ($this->_victim->used) {
            throw new NotEnoughSpaceException();
        }
        list($index, $tag) = $this->_generateIndexTagHash($item);
        return $this->_concreteAdd($index, $tag);
    }

    /**
     * Strategy pattern for add
     *
     * @param int $i
     * @param int $tag
     * @return bool
     */
    public function _concreteAdd(int $i, int $tag): bool
    {
        $curIndex = $i;
        $curTag = $tag;
        $oldTag = null;

        for ($count = 0; $count < KMAXCUCKOOCOUNT; $count++) {
            // first time won't kickout
            $kickout = $count > 0;
            try {
                $oldTag = $this->_table->insertTagToBucket(
                    $curIndex,
                    $curTag,
                    $kickout
                );
            } catch (NotEnoughSpaceException $exc) {
                // catch and next try will go
                $oldTag = null;
            }


            if ($oldTag === NOT_FOUND) {
                $this->_numItems++;
                return true;
            }
            // retry insert in another position if kickout will be done
            if ($kickout) {
                $curTag = $oldTag;
                $curIndex = $this->_altIndex($curIndex, $curTag);
            }
        }

        // if max kickout raised, next time a NotEnoughSpaceException is expected
        $this->_victim->victimize($curIndex, $curTag);
        return false;
    }

    /**
     * Checks if item is inside the table
     *
     * @param int $item
     * @return bool
     */
    public function contain(int $item): bool
    {
        // generate index and 'compress' into a tag
        list($i1, $tag) = $this->_generateIndexTagHash($item);
        $i2 = $this->_altIndex($i1, $tag);

        // ensure health of altIndex
        assert($this->_altIndex($i2, $tag) == $i1);

        // first check if our candidate is not the latest victim
        $found = $this->_victim->used && ($tag == $this->_victim->tag) &&
            ($i1 == $this->_victim->index || $i2 == $this->_victim->index);

        return $found or $this->_table->findTagInBuckets($i1, $i2, $tag);
    }

    /**
     * Deletes an item
     *
     * @param int $item The item to delete
     * @return bool
     */
    public function delete(int $item): bool
    {
        list($i1, $tag) = $this->_generateIndexTagHash($item);
        $i2 = $this->_altIndex($i1, $tag);

        // check if our candidate is not the latest victim
        $found_as_victim = $this->_victim->used && ($tag == $this->_victim->tag) &&
            ($i1 == $this->_victim->index || $i2 == $this->_victim->index);

        if ($found_as_victim) {
            $this->_victim->resurrect();
            return true;
        }

        if ($this->_table->deleteTagFromBucket($i1, $tag) ||
            $this->_table->deleteTagFromBucket($i2, $tag)
        ) {
            $this->_numItems--;
            $this->_tryToResurrectLastVictim();
            return true;
        }

        return false;
    }

    /**
     * Will try to resurrect the last victim since we deleted an
     * item (so more space is available)
     *
     * @return bool
     */
    protected function _tryToResurrectLastVictim(): bool
    {
        if ($this->_victim->used) {
            $this->_victim->resurrect();
            return $this->_concreteAdd($this->_victim->index, $this->_victim->tag);
        }
        return true;
    }

    /**
     * Returns load factor for cuckoo
     *
     * @return float
     */
    public function loadFactor(): float
    {
        return (double)1.0 * $this->_numItems / $this->_table->sizeInTags();
    }

    public function __toString()
    {
        $ss = "";
        $ss .= "CuckooFilter Status:\n";

        $ss .= "\t\t" . ((string)$this->_table) . "\n";
        $ss .= "\t\tKeys stored: " . ((string)$this->_numItems) . "\n";
        $ss .= "\t\tLoad factor: " . ((string)$this->loadFactor()) . "\n";

        if ($this->_numItems > 0) {
            $ss .= "\t\tbit/key:   " . $this->_bitsPerItem . "\n";
        } else {
            $ss .= "\t\tbit/key:   N/A\n";
        }

        return $ss;
    }

}