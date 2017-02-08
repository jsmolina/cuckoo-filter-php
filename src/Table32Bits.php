<?php
declare(strict_types = 1);

namespace Cuckoo;
define('NOT_FOUND', -1);

/**
 * Class Table32Bits implements storage for Cuckoo Filter.
 *
 * @package Cuckoo
 */
class Table32Bits implements \Countable
{
    protected $BITS_PER_TAG = 32;
    protected $TAGS_PER_BUCKET = 4;
    protected $_buckets;
    protected $_numBuckets;

    public function __construct(int $numBuckets)
    {
        $this->_buckets = array_fill(0, $numBuckets, 0);
        $this->_numBuckets = $numBuckets;
    }

    /**
     * Fetches binary shift for position
     * and mask for tag in such position
     *
     * @param int $j the desirable position
     * @return array of shift, mask
     */
    protected function _shiftAndMask(int $j): array
    {
        $shift = 8 * $j;
        $mask = 0xff << $shift;
        return array(
            'shift' => $shift,
            'mask' => $mask);
    }

    /**
     * Fetches a tag from a given position
     *
     * @param int $i expected bucket to insert in
     * @param int $j expected position inside bucket
     * @return int fetched value
     */
    protected function _readTag(int $i, int $j): int
    {
        $s_and_m = $this->_shiftAndMask($j);
        return ($this->_buckets[$i] & $s_and_m['mask']) >> $s_and_m['shift'];
    }

    /**
     * Writes a tag from a given position
     *
     * @param int $i expected bucket to insert in
     * @param int $j expected position inside bucket
     * @param int $tag tag value to insert
     * @return void
     */
    protected function _writeTag(int $i, int $j, int $tag): void
    {
        $s_and_m = $this->_shiftAndMask($j);
        $this->_buckets[$i] |= ($tag << $s_and_m['shift']) & $s_and_m['mask'];
    }

    /**
     * Inserts a tag in specific bucket, finding empty position to do it
     *
     * @param int  $i       Chosen bucket
     * @param int  $tag     Tag value
     * @param bool $kickout Determines if kickout will be done
     * @return int
     * @throws NotEnoughSpaceException
     */
    public function insertTagToBucket(int $i, int $tag, bool $kickout): int
    {
        $oldtag = NOT_FOUND;

        for ($j = 0; $j < $this->TAGS_PER_BUCKET; $j++) {
            if ($this->_readTag($i, $j) == 0) {
                $this->_writeTag($i, $j, $tag);
                return $oldtag;
            }
        }

        if ($kickout) {
            $jRandPos = rand(0, $this->TAGS_PER_BUCKET);
            $oldtag = $this->_readTag($i, $jRandPos);
            $this->_writeTag($i, $jRandPos, $tag);
            return $oldtag;
        }
        throw new NotEnoughSpaceException("Unable to insert tag");
    }

    /**
     * Finds a tag inside two buckets
     *
     * @param int $i1  Bucket pos 1
     * @param int $i2  Bucket pos 2
     * @param int $tag Tag to find
     * @return bool
     */
    public function findTagInBuckets(int $i1, int $i2, int $tag): bool
    {
        for ($j = 0; $j < $this->TAGS_PER_BUCKET; $j++) {
            if ($this->_readTag($i1, $j) == $tag ||
                $this->_readTag($i2, $j) == $tag
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Finds if tag inside a bucket
     *
     * @param int $i   Bucket position
     * @param int $tag Tag to be found
     * @return int
     */
    public function findTagInBucket(int $i, int $tag): int
    {
        for ($j = 0; $j < $this->TAGS_PER_BUCKET; $j++) {
            if ($this->_readTag($i, $j) == $tag) {
                return $j;
            }
        }
        return NOT_FOUND;
    }

    /**
     * Deletes a tag from a bucket
     *
     * @param int $i   bucket position
     * @param int $tag tag value
     * @return bool wether if delete was succeeded
     */
    public function deleteTagFromBucket(int $i, int $tag): bool
    {
        $j = $this->findTagInBucket($i, $tag);
        if ($j != NOT_FOUND) {
            $s_and_m = $this->_shiftAndMask($j);
            $this->_buckets[$i] &= ~(($tag << $s_and_m['shift']) & $s_and_m['mask']);
            return true;
        }
        return false;
    }

    /**
     * Countable implementation
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->_buckets);
    }

    /**
     * Returns current size in tags
     *
     * @return int
     */
    public function sizeInTags(): int
    {
        return $this->TAGS_PER_BUCKET * $this->_numBuckets;
    }

    public function __toString()
    {
        $ss = "";
        $ss .= "SingleHashtable with tag size: " .
            ((string)$this->BITS_PER_TAG) . " bits \n";
        $ss .= "\t\tAssociativity: " . ((string)$this->TAGS_PER_BUCKET) . "\n";
        $ss .= "\t\tTotal # of rows: " . ((string)$this->_numBuckets) . "\n";
        $ss .= "\t\tTotal # slots: " . ((string)$this->sizeInTags()) . "\n";
        return $ss;
    }
}