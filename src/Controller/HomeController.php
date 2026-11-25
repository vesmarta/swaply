<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ListingRepository $listings, CategoryRepository $categories): Response
    {
        return $this->render('home/index.html.twig', [
            'featured' => $listings->findFeatured(4),
            'recent' => $listings->findRecent(8),
            'categories' => $categories->findAllOrdered(),
        ]);
    }
}
