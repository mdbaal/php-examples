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
}
