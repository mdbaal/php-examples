<?php

namespace App\Repository;

use App\Model\Item;
use Psr\Log\LoggerInterface;

class ItemRepository
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getAll(): array
    {
        $this->logger->info('Logged item');

        return $items = [
            new Item('Bert', '$5000'),
            new Item('Albert', '$3000'),
            new Item('Eggbert', '$2000'),
        ];
    }

    public function find(string $name): ?Item
    {
        foreach ($this->getAll() as $item) {
            if ($item->getName() == $name) {
                return $item;
            }
        }

        return null;
    }
}
