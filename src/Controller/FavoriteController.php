<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Listing;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FavoriteController extends AbstractController
{
    #[Route('/favorites', name: 'app_favorites', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(FavoriteRepository $favorites): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('favorite/index.html.twig', [
            'listings' => $favorites->findListingsFor($user),
        ]);
    }

    #[Route('/listing/{id}/favorite', name: 'app_favorite_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(Listing $listing, Request $request, FavoriteRepository $favorites, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('favorite_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $existing = $favorites->findOneBy(['user' => $user, 'listing' => $listing]);

        if (null !== $existing) {
            $em->remove($existing);
            $this->addFlash('info', 'Removed from favorites.');
        } else {
            $em->persist((new Favorite())->setUser($user)->setListing($listing));
            $this->addFlash('success', 'Saved to favorites.');
        }

        $em->flush();

        $referer = $request->headers->get('referer');

        return $referer && str_starts_with($referer, rtrim($request->getSchemeAndHttpHost(), '/'))
            ? $this->redirect($referer)
            : $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
    }
}
