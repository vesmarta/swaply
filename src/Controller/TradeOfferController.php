<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\TradeOffer;
use App\Entity\User;
use App\Enum\CashDirection;
use App\Enum\ListingStatus;
use App\Enum\TradeOfferStatus;
use App\Form\CounterOfferFormType;
use App\Form\TradeOfferFormType;
use App\Repository\ListingRepository;
use App\Repository\TradeOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TradeOfferController extends AbstractController
{
    #[Route('/listing/{id}/offer', name: 'app_offer_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Listing $listing, Request $request, ListingRepository $listingRepo, TradeOfferRepository $offers, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Verify your email address before making trade offers.');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($listing->getSeller() === $user) {
            $this->addFlash('warning', 'You cannot make an offer on your own listing.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (ListingStatus::ACTIVE !== $listing->getStatus() || !$listing->getType()->allowsSwap()) {
            $this->addFlash('warning', 'This listing is not open to swap offers.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (null !== $offers->findPendingBySenderAndListing($user, $listing)) {
            $this->addFlash('info', 'You already have a pending offer on this listing.');

            return $this->redirectToRoute('app_offers');
        }

        $myListings = $listingRepo->findActiveForOfferSelection($user);
        if ([] === $myListings) {
            $this->addFlash('info', 'You need at least one active listing to propose a swap.');

            return $this->redirectToRoute('app_listing_new');
        }

        $offer = new TradeOffer();
        $form = $this->createForm(TradeOfferFormType::class, $offer, ['my_listings' => $myListings]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()
            && $this->applyCash($form, $offer)
            && $this->validateOfferedListing($offer, $user, $form)
        ) {
            $offer
                ->setListing($listing)
                ->setSender($user)
                ->setReceiver($listing->getSeller())
                ->setProposedBy($user)
                ->setStatus(TradeOfferStatus::PENDING);

            $em->persist($offer);
            $em->flush();

            $this->addFlash('success', 'Your swap offer was sent.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        return $this->render('trade/offer_form.html.twig', [
            'form' => $form,
            'listing' => $listing,
            'my_listings' => $myListings,
        ]);
    }

    #[Route('/offers', name: 'app_offers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(TradeOfferRepository $offers): Response
    {
        $offers->expirePending();

        /** @var User $user */
        $user = $this->getUser();
        $related = $offers->findRelated($user);

        $received = array_values(array_filter($related, static fn (TradeOffer $o) => $o->getReceiver() === $user));
        $sent = array_values(array_filter($related, static fn (TradeOffer $o) => $o->getSender() === $user));

        return $this->render('trade/index.html.twig', [
            'received' => $received,
            'sent' => $sent,
        ]);
    }

    #[Route('/offer/{id}', name: 'app_offer_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(TradeOffer $offer, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($offer);

        if ($offer->isExpired()) {
            $offer->setStatus(TradeOfferStatus::EXPIRED);
            $offer->setRespondedAt(new \DateTimeImmutable());
            $em->flush();
        }

        $chain = [];
        for ($node = $offer; null !== $node; $node = $node->getParent()) {
            $chain[] = $node;
        }
        $chain = array_reverse($chain);

        $counterForm = null;
        if ($offer->isAwaitingResponse() && $offer->getProposedBy() !== $user) {
            $draft = new TradeOffer();
            $draft->setCashDirection($offer->getCashDirection());

            $form = $this->createForm(CounterOfferFormType::class, $draft, [
                'cash_labels' => $this->cashLabelsForViewer($offer, $user),
            ]);

            if (CashDirection::NONE !== $offer->getCashDirection()) {
                $form->get('cashAmountTnd')->setData(number_format($offer->getCashAmountMillimes() / 1000, 3, '.', ''));
            }

            $counterForm = $form->createView();
        }

        return $this->render('trade/show.html.twig', [
            'offer' => $offer,
            'chain' => $chain,
            'counterForm' => $counterForm,
        ]);
    }

    #[Route('/offer/{id}/accept', name: 'app_offer_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(TradeOffer $offer, Request $request, TradeOfferRepository $offers, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($offer);
        $this->assertCsrf($request, $offer);
        $this->refreshExpiry($offer, $em);

        if (!$offer->isAwaitingResponse()) {
            $this->addFlash('warning', 'This offer is no longer awaiting a response.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        if ($offer->getProposedBy() === $user) {
            throw $this->createAccessDeniedException('You cannot respond to your own offer.');
        }

        foreach ([$offer->getListing(), $offer->getOfferedListing()] as $listing) {
            if (null !== $listing && ListingStatus::ACTIVE !== $listing->getStatus()) {
                $this->addFlash('warning', 'One of the items is no longer available — the offer cannot be accepted.');

                return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
            }
        }

        $offer->setStatus(TradeOfferStatus::ACCEPTED);
        $offer->setRespondedAt(new \DateTimeImmutable());

        $listingIds = [];
        foreach ([$offer->getListing(), $offer->getOfferedListing()] as $listing) {
            if (null !== $listing) {
                $listing->setStatus(ListingStatus::RESERVED);
                $listingIds[] = $listing->getId();
            }
        }

        $offers->autoRejectFor($listingIds, $offer);
        $em->flush();

        $this->addFlash('success', 'Offer accepted. Both items are reserved until you both confirm the handover.');

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/offer/{id}/reject', name: 'app_offer_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reject(TradeOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($offer);
        $this->assertCsrf($request, $offer);
        $this->refreshExpiry($offer, $em);

        if (!$offer->isAwaitingResponse()) {
            $this->addFlash('warning', 'This offer is no longer awaiting a response.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        if ($offer->getProposedBy() === $user) {
            throw $this->createAccessDeniedException('You cannot respond to your own offer.');
        }

        $offer->setStatus(TradeOfferStatus::REJECTED);
        $offer->setRespondedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Offer declined.');

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/offer/{id}/cancel', name: 'app_offer_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(TradeOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($offer);
        $this->assertCsrf($request, $offer);

        if (!$offer->isAwaitingResponse() || $offer->getProposedBy() !== $user) {
            $this->addFlash('warning', 'Only the author of a pending offer can cancel it.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $offer->setStatus(TradeOfferStatus::CANCELLED);
        $offer->setRespondedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Offer cancelled.');

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/offer/{id}/counter', name: 'app_offer_counter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function counter(TradeOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $this->assertParticipant($offer);
        $this->refreshExpiry($offer, $em);

        if (!$offer->isAwaitingResponse()) {
            $this->addFlash('warning', 'This offer is no longer awaiting a response.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        if ($offer->getProposedBy() === $user) {
            throw $this->createAccessDeniedException('You cannot counter your own offer.');
        }

        foreach ([$offer->getListing(), $offer->getOfferedListing()] as $listing) {
            if (null !== $listing && ListingStatus::ACTIVE !== $listing->getStatus()) {
                $this->addFlash('warning', 'One of the items is no longer available — you cannot counter this offer.');

                return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
            }
        }

        $counter = new TradeOffer();
        $form = $this->createForm(CounterOfferFormType::class, $counter, [
            'cash_labels' => $this->cashLabelsForViewer($offer, $user),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        if ($form->isValid() && $this->applyCash($form, $counter)) {
            $counter
                ->setListing($offer->getListing())
                ->setSender($offer->getSender())
                ->setReceiver($offer->getReceiver())
                ->setOfferedListing($offer->getOfferedListing())
                ->setProposedBy($user)
                ->setParent($offer)
                ->setStatus(TradeOfferStatus::PENDING);

            $offer->setStatus(TradeOfferStatus::COUNTERED);
            $offer->setRespondedAt(new \DateTimeImmutable());

            $em->persist($counter);
            $em->flush();

            $this->addFlash('success', 'Counteroffer sent.');

            return $this->redirectToRoute('app_offer_show', ['id' => $counter->getId()]);
        }

        $chain = [];
        for ($node = $offer; null !== $node; $node = $node->getParent()) {
            $chain[] = $node;
        }

        return new Response($this->renderView('trade/show.html.twig', [
            'offer' => $offer,
            'chain' => array_reverse($chain),
            'counterForm' => $form->createView(),
            'counterOpen' => true,
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/offer/{id}/confirm', name: 'app_offer_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function confirm(TradeOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($offer);
        $this->assertCsrf($request, $offer);

        if (TradeOfferStatus::ACCEPTED !== $offer->getStatus()) {
            $this->addFlash('warning', 'This trade is not awaiting confirmation.');

            return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
        }

        $now = new \DateTimeImmutable();

        if ($offer->getSender() === $user) {
            if (null !== $offer->getRequesterConfirmedAt()) {
                $this->addFlash('info', 'You already confirmed this trade.');

                return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
            }
            $offer->setRequesterConfirmedAt($now);
        } else {
            if (null !== $offer->getOwnerConfirmedAt()) {
                $this->addFlash('info', 'You already confirmed this trade.');

                return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
            }
            $offer->setOwnerConfirmedAt($now);
        }

        if ($offer->bothPartiesConfirmed()) {
            $offer->setStatus(TradeOfferStatus::COMPLETED);
            foreach ([$offer->getListing(), $offer->getOfferedListing()] as $listing) {
                if (null !== $listing) {
                    $listing->setStatus(ListingStatus::TRADED);
                }
            }
            $this->addFlash('success', 'Trade completed! Both items are now marked as traded.');
        } else {
            $this->addFlash('success', 'Confirmation saved — waiting for the other party.');
        }

        $em->flush();

        return $this->redirectToRoute('app_offer_show', ['id' => $offer->getId()]);
    }

    private function applyCash(FormInterface $form, TradeOffer $offer): bool
    {
        $direction = $offer->getCashDirection();
        $raw = trim((string) $form->get('cashAmountTnd')->getData());

        if (CashDirection::NONE === $direction) {
            $offer->setCashAmountMillimes(0);

            return true;
        }

        if ('' === $raw) {
            $form->get('cashAmountTnd')->addError(new FormError('Enter the cash amount, or choose "Straight swap — no cash".'));

            return false;
        }

        $normalized = str_replace([' ', "\u{00A0}"], '', $raw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }
        if (!is_numeric($normalized) || (float) $normalized <= 0) {
            $form->get('cashAmountTnd')->addError(new FormError('Enter a valid cash amount in TND, e.g. 50.000.'));

            return false;
        }

        $offer->setCashAmountMillimes((int) round((float) $normalized * 1000));

        return true;
    }

    private function validateOfferedListing(TradeOffer $offer, User $user, FormInterface $form): bool
    {
        $offered = $offer->getOfferedListing();

        if (null === $offered) {
            $form->get('offeredListing')->addError(new FormError('Please choose which of your items to offer.'));

            return false;
        }

        if ($offered->getSeller() !== $user || ListingStatus::ACTIVE !== $offered->getStatus()) {
            $form->get('offeredListing')->addError(new FormError('Only your active listings can be offered.'));

            return false;
        }

        return true;
    }

    /** @return array<string, string> Labels keyed by CashDirection value, from the viewer's perspective. */
    private function cashLabelsForViewer(TradeOffer $offer, User $viewer): array
    {
        if ($viewer === $offer->getSender()) {
            return [
                CashDirection::NONE->value => 'No cash involved',
                CashDirection::REQUESTER_PAYS->value => 'I pay cash on top',
                CashDirection::OWNER_PAYS->value => 'They pay me cash on top',
            ];
        }

        return [
            CashDirection::NONE->value => 'No cash involved',
            CashDirection::REQUESTER_PAYS->value => 'They pay me cash on top',
            CashDirection::OWNER_PAYS->value => 'I pay cash on top',
        ];
    }

    private function refreshExpiry(TradeOffer $offer, EntityManagerInterface $em): void
    {
        if ($offer->isExpired()) {
            $offer->setStatus(TradeOfferStatus::EXPIRED);
            $offer->setRespondedAt(new \DateTimeImmutable());
            $em->flush();
        }
    }

    private function assertParticipant(TradeOffer $offer): void
    {
        $user = $this->getUser();
        if ($user !== $offer->getSender() && $user !== $offer->getReceiver() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not part of this trade.');
        }
    }

    private function assertCsrf(Request $request, TradeOffer $offer): void
    {
        if (!$this->isCsrfTokenValid('offer_action_'.$offer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }
}
