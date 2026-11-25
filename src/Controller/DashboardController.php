<?php

namespace App\Controller;

use App\Entity\Purchase;
use App\Entity\TradeOffer;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Enum\PurchaseStatus;
use App\Repository\ListingRepository;
use App\Repository\MessageRepository;
use App\Repository\PurchaseRepository;
use App\Repository\ReviewRepository;
use App\Repository\TradeOfferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        ListingRepository $listings,
        TradeOfferRepository $offers,
        PurchaseRepository $purchases,
        MessageRepository $messages,
        ReviewRepository $reviews,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $myListings = $listings->findBySeller($user);
        $activeListings = count(array_filter($myListings, static fn ($l) => ListingStatus::ACTIVE === $l->getStatus()));

        $relatedOffers = $offers->findRelated($user);
        $awaitingOffers = count(array_filter(
            $relatedOffers,
            static fn (TradeOffer $o) => $o->isAwaitingResponse() && $o->getProposedBy() !== $user,
        ));

        $relatedPurchases = $purchases->findRelated($user);
        $openPurchases = count(array_filter(
            $relatedPurchases,
            static fn (Purchase $p) => in_array($p->getStatus(), [PurchaseStatus::PENDING, PurchaseStatus::ACCEPTED], true),
        ));

        return $this->render('dashboard/index.html.twig', [
            'activeListings' => $activeListings,
            'awaitingOffers' => $awaitingOffers,
            'openPurchases' => $openPurchases,
            'unreadMessages' => $messages->countUnreadForUser($user),
            'rating' => $reviews->statsFor($user),
        ]);
    }
}
