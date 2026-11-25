<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Repository\ListingRepository;
use App\Repository\PurchaseRepository;
use App\Repository\TradeOfferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(ListingRepository $listings, TradeOfferRepository $offers, PurchaseRepository $purchases, UserRepository $users): Response
    {
        return $this->render('admin/index.html.twig', [
            'stats' => [
                'users' => $users->count([]),
                'activeListings' => $listings->countByStatus(ListingStatus::ACTIVE),
                'pendingListings' => $listings->countByStatus(ListingStatus::PENDING),
                'completedTrades' => $offers->countCompleted(),
                'completedPurchases' => $purchases->countCompleted(),
            ],
            'pending' => $listings->findPending(),
        ]);
    }

    #[Route('/admin/listing/{id}/approve', name: 'app_admin_listing_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Listing $listing, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertCsrf($request, $listing);

        if (ListingStatus::PENDING !== $listing->getStatus()) {
            $this->addFlash('warning', 'Only pending listings can be approved.');

            return $this->redirectToRoute('app_admin');
        }

        $listing->setStatus(ListingStatus::ACTIVE);
        $listing->setRejectionReason(null);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" is now live on the marketplace.', $listing->getTitle()));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/listing/{id}/reject', name: 'app_admin_listing_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Listing $listing, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertCsrf($request, $listing);

        if (ListingStatus::PENDING !== $listing->getStatus()) {
            $this->addFlash('warning', 'Only pending listings can be rejected.');

            return $this->redirectToRoute('app_admin');
        }

        $reason = trim((string) $request->request->get('reason'));

        $listing->setStatus(ListingStatus::REJECTED);
        $listing->setRejectionReason('' !== $reason ? $reason : 'Did not meet the marketplace guidelines.');
        $em->flush();

        $this->addFlash('success', sprintf('"%s" was rejected and the seller was informed.', $listing->getTitle()));

        return $this->redirectToRoute('app_admin');
    }

    private function assertCsrf(Request $request, Listing $listing): void
    {
        if (!$this->isCsrfTokenValid('admin_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function userIndex(Request $request, UserRepository $users): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->render('admin/users.html.twig', [
            'users' => $users->searchUsers($q),
            'q' => $q,
            'stats' => [
                'total' => $users->count([]),
                'active' => $users->count(['isActive' => true]),
                'suspended' => $users->count(['isActive' => false]),
            ],
        ]);
    }

    #[Route('/admin/user/{id}/toggle', name: 'app_admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        if ($user === $admin) {
            $this->addFlash('warning', 'You cannot suspend your own account.');

            return $this->redirectToRoute('app_admin_users', ['q' => (string) $request->request->get('q', '')]);
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('warning', 'Administrator accounts cannot be suspended.');

            return $this->redirectToRoute('app_admin_users', ['q' => (string) $request->request->get('q', '')]);
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $this->addFlash('success', sprintf(
            '%s %s.',
            $user->getUsername(),
            $user->isActive() ? 'was reactivated' : 'was suspended',
        ));

        return $this->redirectToRoute('app_admin_users', ['q' => (string) $request->request->get('q', '')]);
    }
}
