<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\Purchase;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Enum\PurchaseStatus;
use App\Form\PurchaseFormType;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PurchaseController extends AbstractController
{
    #[Route('/listing/{id}/buy', name: 'app_purchase_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Listing $listing, Request $request, PurchaseRepository $purchases, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Verify your email address before buying items.');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($listing->getSeller() === $user) {
            $this->addFlash('warning', 'You cannot buy your own listing.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (ListingStatus::ACTIVE !== $listing->getStatus() || !$listing->getType()->allowsSale() || null === $listing->getPriceMillimes()) {
            $this->addFlash('warning', 'This listing is not available for direct purchase.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (null !== $purchases->findPendingByBuyerAndListing($user, $listing)) {
            $this->addFlash('info', 'You already have a pending purchase request on this listing.');

            return $this->redirectToRoute('app_purchases');
        }

        $form = $this->createForm(PurchaseFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $purchase = (new Purchase())
                ->setListing($listing)
                ->setBuyer($user)
                ->setSeller($listing->getSeller())
                ->setPriceMillimes($listing->getPriceMillimes())
                ->setMessage($form->get('message')->getData())
                ->setStatus(PurchaseStatus::PENDING);

            $em->persist($purchase);
            $em->flush();

            $this->addFlash('success', 'Purchase request sent to the seller.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('purchase/buy.html.twig', [
            'listing' => $listing,
            'form' => $form,
        ]);
    }

    #[Route('/purchases', name: 'app_purchases', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(PurchaseRepository $purchases): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $related = $purchases->findRelated($user);

        $buying = array_values(array_filter($related, static fn (Purchase $p) => $p->getBuyer() === $user));
        $selling = array_values(array_filter($related, static fn (Purchase $p) => $p->getSeller() === $user));

        return $this->render('purchase/index.html.twig', [
            'buying' => $buying,
            'selling' => $selling,
        ]);
    }

    #[Route('/purchase/{id}', name: 'app_purchase_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Purchase $purchase): Response
    {
        $this->assertParticipant($purchase);

        return $this->render('purchase/show.html.twig', [
            'purchase' => $purchase,
        ]);
    }

    #[Route('/purchase/{id}/accept', name: 'app_purchase_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(Purchase $purchase, Request $request, PurchaseRepository $purchases, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($purchase);
        $this->assertCsrf($request, $purchase);

        if ($purchase->getSeller() !== $user) {
            throw $this->createAccessDeniedException('Only the seller can accept a purchase request.');
        }

        if (PurchaseStatus::PENDING !== $purchase->getStatus()) {
            $this->addFlash('warning', 'This purchase request is no longer pending.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        if (ListingStatus::ACTIVE !== $purchase->getListing()->getStatus()) {
            $this->addFlash('warning', 'This listing is no longer available — the purchase cannot be accepted.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        $purchase->setStatus(PurchaseStatus::ACCEPTED);
        $purchase->setRespondedAt(new \DateTimeImmutable());
        $purchase->getListing()->setStatus(ListingStatus::RESERVED);

        $purchases->autoRejectFor([$purchase->getListing()->getId()], $purchase);
        $em->flush();

        $this->addFlash('success', 'Purchase accepted. The item is reserved until you both confirm the handover.');

        return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/purchase/{id}/reject', name: 'app_purchase_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reject(Purchase $purchase, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($purchase);
        $this->assertCsrf($request, $purchase);

        if ($purchase->getSeller() !== $user) {
            throw $this->createAccessDeniedException('Only the seller can decline a purchase request.');
        }

        if (PurchaseStatus::PENDING !== $purchase->getStatus()) {
            $this->addFlash('warning', 'This purchase request is no longer pending.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        $purchase->setStatus(PurchaseStatus::REJECTED);
        $purchase->setRespondedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Purchase request declined.');

        return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/purchase/{id}/cancel', name: 'app_purchase_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Purchase $purchase, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($purchase);
        $this->assertCsrf($request, $purchase);

        if ($purchase->getBuyer() !== $user || PurchaseStatus::PENDING !== $purchase->getStatus()) {
            $this->addFlash('warning', 'Only the buyer can cancel a pending purchase request.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        $purchase->setStatus(PurchaseStatus::CANCELLED);
        $purchase->setRespondedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Purchase request cancelled.');

        return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/purchase/{id}/confirm', name: 'app_purchase_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function confirm(Purchase $purchase, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($purchase);
        $this->assertCsrf($request, $purchase);

        if (PurchaseStatus::ACCEPTED !== $purchase->getStatus()) {
            $this->addFlash('warning', 'This purchase is not awaiting confirmation.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
        }

        $now = new \DateTimeImmutable();

        if ($purchase->getBuyer() === $user) {
            if (null !== $purchase->getBuyerConfirmedAt()) {
                $this->addFlash('info', 'You already confirmed this purchase.');

                return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
            }
            $purchase->setBuyerConfirmedAt($now);
        } else {
            if (null !== $purchase->getSellerConfirmedAt()) {
                $this->addFlash('info', 'You already confirmed this purchase.');

                return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
            }
            $purchase->setSellerConfirmedAt($now);
        }

        if ($purchase->bothPartiesConfirmed()) {
            $purchase->setStatus(PurchaseStatus::COMPLETED);
            $purchase->getListing()->setStatus(ListingStatus::SOLD);
            $this->addFlash('success', 'Purchase completed! The item is now marked as sold.');
        } else {
            $this->addFlash('success', 'Confirmation saved — waiting for the other party.');
        }

        $em->flush();

        return $this->redirectToRoute('app_purchase_show', ['id' => $purchase->getId()]);
    }

    private function assertParticipant(Purchase $purchase): void
    {
        $user = $this->getUser();
        if ($user !== $purchase->getBuyer() && $user !== $purchase->getSeller() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not part of this purchase.');
        }
    }

    private function assertCsrf(Request $request, Purchase $purchase): void
    {
        if (!$this->isCsrfTokenValid('purchase_action_'.$purchase->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }
}
