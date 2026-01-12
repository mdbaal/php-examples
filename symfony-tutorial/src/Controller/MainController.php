<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/')]
    public function index(ItemRepository $repository): Response
    {
        $items = $repository->getAll();

        $itemCount = count($items);

        return $this->render('main/homepage.html.twig', [
            'message' => 'Helloo',
            'items' => $items,
            'itemCount' => $itemCount,
        ]);
    }
}
