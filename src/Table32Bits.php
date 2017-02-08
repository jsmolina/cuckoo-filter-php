<?php
declare(strict_types = 1);

namespace Cuckoo;
define('NOT_FOUND', -1);

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

    protected function _shiftAndMask(int $j): array
    {
        $shift = 8 * $j;
        $mask = 0xff << $shift;
        return array(
            'shift' => $shift,
            'mask' => $mask);
    }

    protected function _readTag(int $i, int $j): int
    {
        $s_and_m = $this->_shiftAndMask($j);
        return ($this->_buckets[$i] & $s_and_m['mask']) >> $s_and_m['shift'];
    }

    protected function _writeTag(int $i, int $j, int $tag): void
    {
        $s_and_m = $this->_shiftAndMask($j);
        $this->_buckets[$i] |= ($tag << $s_and_m['shift']) & $s_and_m['mask'];
    }

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

    public function findTagInBuckets(int $i1, int $i2, int $tag): bool
    {
        for ($j = 0; $j < $this->TAGS_PER_BUCKET; $j++) {
            if ($this->_readTag($i1, $j) == $tag or $this->_readTag($i2, $j) == $tag) {
                return true;
            }
        }
        return false;
    }

    public function findTagInBucket(int $i, int $tag): int
    {
        for ($j = 0; $j < $this->TAGS_PER_BUCKET; $j++) {
            if ($this->_readTag($i, $j) == $tag) {
                return $j;
            }
        }
        return NOT_FOUND;
    }

    public function deleteTagFromBucket($i, $tag): bool
    {
        $j = $this->findTagInBucket($i, $tag);
        if ($j != NOT_FOUND) {
            $s_and_m = $this->_shiftAndMask($j);
            $this->_buckets[$i] &= ~(($tag << $s_and_m['shift']) & $s_and_m['mask']);
            return true;
        }
        return false;
    }

    public function count(): int
    {
        return count($this->_buckets);
    }

    public function sizeInTags(): int
    {
        return $this->TAGS_PER_BUCKET * $this->_numBuckets;
    }

    public function __toString()
    {
        $ss = "";
        $ss .= "SingleHashtable with tag size: " . ((string)$this->BITS_PER_TAG) . " bits \n";
        $ss .= "\t\tAssociativity: " . ((string)$this->TAGS_PER_BUCKET) . "\n";
        $ss .= "\t\tTotal # of rows: " . ((string)$this->_numBuckets) . "\n";
        $ss .= "\t\tTotal # slots: " . ((string)$this->sizeInTags()) . "\n";
        return $ss;
    }
}