<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Repository\PurchaseRepository;
use App\Repository\ReviewRepository;
use App\Repository\TradeOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewController extends AbstractController
{
    #[Route('/listing/{id}/review', name: 'app_review_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Listing $listing,
        Request $request,
        ReviewRepository $reviews,
        TradeOfferRepository $offers,
        PurchaseRepository $purchases,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!in_array($listing->getStatus(), [ListingStatus::TRADED, ListingStatus::SOLD], true)) {
            $this->addFlash('warning', 'You can only review a deal once it is completed.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        $counterpart = $this->resolveCounterpart($listing, $user, $offers, $purchases);

        if (null === $counterpart) {
            $this->addFlash('warning', 'Only the two people who completed this deal can review each other.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (null !== $reviews->findOneByAuthorAndListing($user, $listing)) {
            $this->addFlash('info', 'You already reviewed this deal.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        $form = $this->createFormBuilder()
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '★★★★★ — Excellent' => 5,
                    '★★★★ — Good' => 4,
                    '★★★ — Okay' => 3,
                    '★★ — Poor' => 2,
                    '★ — Bad' => 1,
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comment (optional)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'How was the communication, the item, the handover?'],
                'constraints' => [new Assert\Length(max: 1000)],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review = (new Review())
                ->setListing($listing)
                ->setAuthor($user)
                ->setSubject($counterpart)
                ->setRating((int) $form->get('rating')->getData())
                ->setComment($form->get('comment')->getData());

            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Thanks! Your review was published.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        return $this->render('review/form.html.twig', [
            'listing' => $listing,
            'counterpart' => $counterpart,
            'form' => $form,
        ]);
    }

    private function resolveCounterpart(Listing $listing, User $user, TradeOfferRepository $offers, PurchaseRepository $purchases): ?User
    {
        $trade = $offers->findCompletedForListing($listing);

        if (null !== $trade) {
            if ($trade->getSender() === $user) {
                return $trade->getReceiver();
            }
            if ($trade->getReceiver() === $user) {
                return $trade->getSender();
            }
        }

        $purchase = $purchases->findCompletedForListing($listing);

        if (null !== $purchase) {
            if ($purchase->getBuyer() === $user) {
                return $purchase->getSeller();
            }
            if ($purchase->getSeller() === $user) {
                return $purchase->getBuyer();
            }
        }

        return null;
    }
}
