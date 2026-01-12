<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/items')]
    public function items(ItemRepository $repository): Response
    {
        return $this->json($repository->getAll());
    }

    #[Route('/items/{name}', methods: ['GET'])]
    public function get(string $name, ItemRepository $repository): Response
    {
        $item = $repository->find($name);

        if (!$item) {
            throw $this->createNotFoundException('No item with that name found');
        }

        return $this->json($item);
    }
}
