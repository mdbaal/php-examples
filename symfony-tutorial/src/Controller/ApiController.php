<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Item;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/items')]
    public function items(): Response
    {
        $items = [
            new Item('Bert', '$5000'),
            new Item('Albert', '$3000'),
            new Item('Eggbert', '$2000'),
        ];

        return $this->json($items);
    }
}
