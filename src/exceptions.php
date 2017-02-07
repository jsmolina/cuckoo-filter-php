<?php
declare(strict_types=1);

namespace Cuckoo;

class CuckooException extends \Exception {

}

class NotEnoughSpaceException extends CuckooException {

}

class NotSupportedException extends CuckooException {

}

class NotFoundException extends CuckooException {

}