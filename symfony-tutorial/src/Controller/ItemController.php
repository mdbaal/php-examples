<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ItemController extends AbstractController
{
    #[Route('/items/{name}', 'app_item_show')]
    public function show(string $name, ItemRepository $repository): Response
    {
        $item = $repository->find($name);

        if (!$item) {
            throw $this->createNotFoundException('No item with that name found');
        }

        return $this->render('item/show.html.twig', [
            'item' => $item,
        ]);
    }
}
