<?php
namespace AppZap\Migrator\DirectoryIterator;

use ArrayObject;
use DirectoryIterator;
use IteratorAggregate;
use Traversable;

class SortableDirectoryIterator implements IteratorAggregate
{

    /**
     * @var ArrayObject
     */
    private $_storage;

    public function __construct(string $path)
    {
        $this->_storage = new ArrayObject();

        $files = new DirectoryIterator($path);
        /** @var $file DirectoryIterator */
        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }
            $this->_storage->offsetSet($file->getFilename(), $file->getFileInfo());
        }
        $this->_storage->uksort(
            function ($a, $b) {
                return strcmp($a, $b);
            }
        );
    }

    public function getIterator(): Traversable
    {
        return $this->_storage->getIterator();
    }
}
