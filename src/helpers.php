<?php
declare(strict_types=1);

namespace Cuckoo;

/**
 * Class VictimCache stores the last victim from 'space conflicts'
 *
 * @package Cuckoo
 */
class VictimCache
{
    public $index = 0;
    public $tag = 0;
    public $used = NOT_USED_VICTIM;

    public function victimize($index=0, $tag=0)
    {
        $this->index = $index;
        $this->tag = $tag;
        $this->used = USED_VICTIM;
    }

    public function resurrect()
    {
        $this->used = NOT_USED_VICTIM;
    }
}