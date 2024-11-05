<?php

namespace Portrino\PxDbmigrator\DirectoryIterator;

/**
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class SortableDirectoryIterator implements \IteratorAggregate
{
    /**
     * @var \ArrayObject<string, \SplFileInfo>
     */
    private \ArrayObject $storage;

    public function __construct(string $path)
    {
        $this->storage = new \ArrayObject();

        $files = new \DirectoryIterator($path);
        /** @var \DirectoryIterator $file */
        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }
            $this->storage->offsetSet($file->getFilename(), $file->getFileInfo());
        }
        $this->storage->uksort(
            function (string $a, string $b) {
                return strcmp($a, $b);
            }
        );
    }

    public function getIterator(): \Traversable
    {
        return $this->storage->getIterator();
    }
}
