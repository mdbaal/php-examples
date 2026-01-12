<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/items')]
    public function items(ItemRepository $repository): Response
    {
        return $this->json($repository->getAll());
    }
}
