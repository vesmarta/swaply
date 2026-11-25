<?php

namespace App\Controller;

use App\Constant\Governorates;
use App\Entity\Listing;
use App\Entity\ListingImage;
use App\Entity\User;
use App\Enum\ListingCondition;
use App\Enum\ListingStatus;
use App\Form\ListingFormType;
use App\Repository\CategoryRepository;
use App\Repository\ListingRepository;
use App\Repository\PurchaseRepository;
use App\Repository\ReviewRepository;
use App\Repository\TradeOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ListingController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/listings';
    private const PER_PAGE = 12;
    private const MAX_IMAGES = 5;

    #[Route('/marketplace', name: 'app_marketplace', methods: ['GET'])]
    public function marketplace(Request $request, ListingRepository $listings, CategoryRepository $categories): Response
    {
        $rawFilters = [
            'q' => trim((string) $request->query->get('q', '')),
            'category' => (string) $request->query->get('category', ''),
            'governorate' => (string) $request->query->get('governorate', ''),
            'condition' => (string) $request->query->get('condition', ''),
            'type' => (string) $request->query->get('type', ''),
            'min' => trim((string) $request->query->get('min', '')),
            'max' => trim((string) $request->query->get('max', '')),
        ];

        $filters = $rawFilters;
        $filters['min'] = $this->tndToMillimes($rawFilters['min']);
        $filters['max'] = $this->tndToMillimes($rawFilters['max']);

        $sort = (string) $request->query->get('sort', 'newest');
        if (!\in_array($sort, ['newest', 'price_asc', 'price_desc', 'popular'], true)) {
            $sort = 'newest';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $result = $listings->search($filters, $sort, $page, self::PER_PAGE);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return $this->render('marketplace/index.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => $pages,
            'sort' => $sort,
            'filters' => $rawFilters,
            'categories' => $categories->findAllOrdered(),
            'conditions' => ListingCondition::cases(),
            'governorates' => Governorates::ALL,
        ]);
    }

    #[Route('/listing/{id}', name: 'app_listing_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Listing $listing,
        EntityManagerInterface $em,
        ReviewRepository $reviews,
        TradeOfferRepository $offers,
        PurchaseRepository $purchases,
    ): Response {
        $user = $this->getUser();
        $isOwner = $user instanceof User && $listing->getSeller() === $user;

        if (!$listing->getStatus()->isVisibleToPublic() && !$isOwner && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('This listing is not available.');
        }

        if (!$isOwner) {
            $listing->incrementViewCount();
            $em->flush();
        }

        $canReview = false;
        if ($user instanceof User && in_array($listing->getStatus(), [ListingStatus::TRADED, ListingStatus::SOLD], true)) {
            $isParticipant = $this->participatesInDeal($listing, $user, $offers, $purchases);
            $canReview = $isParticipant && null === $reviews->findOneByAuthorAndListing($user, $listing);
        }

        return $this->render('listing/show.html.twig', [
            'listing' => $listing,
            'sellerStats' => $reviews->statsFor($listing->getSeller()),
            'sellerReviews' => $reviews->findRecentForSubject($listing->getSeller()),
            'canReview' => $canReview,
        ]);
    }

    private function participatesInDeal(
        Listing $listing,
        User $user,
        TradeOfferRepository $offers,
        PurchaseRepository $purchases,
    ): bool {
        $trade = $offers->findCompletedForListing($listing);
        if (null !== $trade && ($trade->getSender() === $user || $trade->getReceiver() === $user)) {
            return true;
        }

        $purchase = $purchases->findCompletedForListing($listing);

        return null !== $purchase && ($purchase->getBuyer() === $user || $purchase->getSeller() === $user);
    }

    #[Route('/listing/new', name: 'app_listing_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Verify your email address before publishing listings.');

            return $this->redirectToRoute('app_dashboard');
        }

        $listing = new Listing();
        $listing->setSeller($user);
        if ($user->getGovernorate()) {
            $listing->setGovernorate($user->getGovernorate());
        }
        if ($user->getCity()) {
            $listing->setCity($user->getCity());
        }

        $form = $this->createForm(ListingFormType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()
            && $this->applyPrice($form, $listing)
            && $this->processUploads($form, $listing, true)
        ) {
            $listing->setStatus(ListingStatus::PENDING);
            $em->persist($listing);
            $em->flush();

            $this->addFlash('success', 'Your listing was submitted and is awaiting moderation.');

            return $this->redirectToRoute('app_my_listings');
        }

        return $this->render('listing/form.html.twig', [
            'form' => $form,
            'listing' => $listing,
            'isEdit' => false,
        ]);
    }

    #[Route('/listing/{id}/edit', name: 'app_listing_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Listing $listing, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($listing);

        if (!\in_array($listing->getStatus(), [ListingStatus::PENDING, ListingStatus::ACTIVE, ListingStatus::REJECTED], true)) {
            $this->addFlash('warning', 'This listing can no longer be edited.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        $form = $this->createForm(ListingFormType::class, $listing, [
            'price_tnd' => null !== $listing->getPriceMillimes()
                ? number_format($listing->getPriceMillimes() / 1000, 3, '.', '')
                : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()
            && $this->applyPrice($form, $listing)
            && $this->processUploads($form, $listing, false)
        ) {
            if (ListingStatus::REJECTED === $listing->getStatus()) {
                $listing->setStatus(ListingStatus::PENDING);
                $listing->setRejectionReason(null);
                $this->addFlash('success', 'Changes saved — your listing was resubmitted for moderation.');
            } else {
                $this->addFlash('success', 'Listing updated.');
            }
            $em->flush();

            return $this->redirectToRoute('app_my_listings');
        }

        return $this->render('listing/form.html.twig', [
            'form' => $form,
            'listing' => $listing,
            'isEdit' => true,
        ]);
    }

    #[Route('/my-listings', name: 'app_my_listings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myListings(ListingRepository $listings): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('listing/my_listings.html.twig', [
            'listings' => $listings->findBySeller($user),
        ]);
    }

    #[Route('/listing/{id}/withdraw', name: 'app_listing_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdraw(Listing $listing, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($listing);
        $this->assertCsrf($request, $listing);

        if (ListingStatus::ACTIVE === $listing->getStatus()) {
            $listing->setStatus(ListingStatus::WITHDRAWN);
            $em->flush();
            $this->addFlash('success', 'Listing withdrawn from the marketplace.');
        } else {
            $this->addFlash('warning', 'Only active listings can be withdrawn.');
        }

        return $this->redirectToRoute('app_my_listings');
    }

    #[Route('/listing/{id}/delete', name: 'app_listing_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Listing $listing, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($listing);
        $this->assertCsrf($request, $listing);

        if (!\in_array($listing->getStatus(), [ListingStatus::PENDING, ListingStatus::REJECTED, ListingStatus::WITHDRAWN], true)) {
            $this->addFlash('warning', 'Active listings must be withdrawn before deletion.');

            return $this->redirectToRoute('app_my_listings');
        }

        $this->removeFiles($listing);
        $em->remove($listing);
        $em->flush();

        $this->addFlash('success', 'Listing deleted.');

        return $this->redirectToRoute('app_my_listings');
    }

    #[Route('/listing/{id}/image/{imageId}/delete', name: 'app_listing_image_delete', requirements: ['id' => '\d+', 'imageId' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteImage(Listing $listing, int $imageId, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessOwner($listing);
        $this->assertCsrf($request, $listing);

        foreach ($listing->getImages() as $image) {
            if ($image->getId() !== $imageId) {
                continue;
            }

            if ($listing->getImages()->count() <= 1) {
                $this->addFlash('warning', 'A listing must keep at least one photo.');

                return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
            }

            $path = $this->getParameter('kernel.project_dir').'/public/'.self::UPLOAD_DIR.'/'.$image->getFilename();
            if (is_file($path)) {
                @unlink($path);
            }

            $listing->removeImage($image);
            $em->flush();
            $this->addFlash('success', 'Photo removed.');

            return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
        }

        throw $this->createNotFoundException('Photo not found.');
    }

    private function applyPrice(FormInterface $form, Listing $listing): bool
    {
        $raw = trim((string) $form->get('priceTnd')->getData());

        if ('' === $raw) {
            if ($listing->getType()->allowsSale()) {
                $form->get('priceTnd')->addError(new FormError('Please enter a price for a sale listing.'));

                return false;
            }

            $listing->setPriceMillimes(null);

            return true;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        if (!is_numeric($normalized) || (float) $normalized <= 0) {
            $form->get('priceTnd')->addError(new FormError('Enter a valid price in TND, e.g. 89.500.'));

            return false;
        }

        $listing->setPriceMillimes((int) round((float) $normalized * 1000));

        return true;
    }

    private function processUploads(FormInterface $form, Listing $listing, bool $requireAtLeastOne): bool
    {
        /** @var list<UploadedFile> $files */
        $files = $form->get('imageFiles')->getData() ?? [];
        $existing = $listing->getImages()->count();

        if ([] === $files && $requireAtLeastOne && 0 === $existing) {
            $form->get('imageFiles')->addError(new FormError('Please add at least one photo.'));

            return false;
        }

        if (\count($files) + $existing > self::MAX_IMAGES) {
            $form->get('imageFiles')->addError(new FormError('A listing can have at most '.self::MAX_IMAGES.' photos.'));

            return false;
        }

        $dir = $this->getParameter('kernel.project_dir').'/public/'.self::UPLOAD_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($files as $file) {
            $filename = 'listing-'.bin2hex(random_bytes(8)).'.'.($file->guessExtension() ?: 'jpg');
            $file->move($dir, $filename);

            $image = new ListingImage();
            $image->setFilename($filename);
            $listing->addImage($image);
        }

        return true;
    }

    private function removeFiles(Listing $listing): void
    {
        foreach ($listing->getImages() as $image) {
            $path = $this->getParameter('kernel.project_dir').'/public/'.self::UPLOAD_DIR.'/'.$image->getFilename();
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function denyUnlessOwner(Listing $listing): void
    {
        if ($listing->getSeller() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this listing.');
        }
    }

    private function assertCsrf(Request $request, Listing $listing): void
    {
        if (!$this->isCsrfTokenValid('listing_action_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }

    private function tndToMillimes(string $raw): ?int
    {
        if ('' === $raw) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            return null;
        }

        return (int) round((float) $normalized * 1000);
    }
}
