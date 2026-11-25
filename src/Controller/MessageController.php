<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Listing;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_messages', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ConversationRepository $conversations, MessageRepository $messages): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $list = $conversations->findForUser($user);
        $ids = array_map(static fn (Conversation $c) => $c->getId(), $list);

        $lastMessages = $messages->findLastByConversations($ids);
        $unread = $messages->countUnreadByConversations($ids, $user);

        $rows = [];
        foreach ($list as $conversation) {
            $rows[] = [
                'conversation' => $conversation,
                'other' => $conversation->getOther($user),
                'lastMessage' => $lastMessages[$conversation->getId()] ?? null,
                'unread' => $unread[$conversation->getId()] ?? 0,
            ];
        }

        return $this->render('message/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/listing/{id}/message', name: 'app_message_seller', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function messageSeller(Listing $listing, ConversationRepository $conversations, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Verify your email address before messaging sellers.');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($listing->getSeller() === $user) {
            $this->addFlash('warning', 'You cannot message yourself.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (!in_array($listing->getStatus(), [ListingStatus::ACTIVE, ListingStatus::RESERVED, ListingStatus::TRADED, ListingStatus::SOLD], true)) {
            $this->addFlash('warning', 'This listing is not open for messages.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        $conversation = $conversations->findBetween($listing, $user, $listing->getSeller());

        if (null === $conversation) {
            [$a, $b] = $user->getId() < $listing->getSeller()->getId()
                ? [$user, $listing->getSeller()]
                : [$listing->getSeller(), $user];

            $conversation = (new Conversation())
                ->setListing($listing)
                ->setUserA($a)
                ->setUserB($b);

            $em->persist($conversation);
            $em->flush();
        }

        return $this->redirectToRoute('app_message_thread', ['id' => $conversation->getId()]);
    }

    #[Route('/messages/{id}', name: 'app_message_thread', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function thread(Conversation $conversation, MessageRepository $messages): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($conversation);

        $thread = $messages->findForConversation($conversation);
        $messages->markRead($conversation, $user);

        return $this->render('message/thread.html.twig', [
            'conversation' => $conversation,
            'other' => $conversation->getOther($user),
            'messages' => $thread,
        ]);
    }

    #[Route('/messages/{id}/send', name: 'app_message_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function send(Conversation $conversation, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertParticipant($conversation);

        if (!$this->isCsrfTokenValid('message_send_'.$conversation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $body = trim((string) $request->request->get('body'));

        if ('' === $body) {
            $this->addFlash('warning', 'Write a message before sending.');

            return $this->redirectToRoute('app_message_thread', ['id' => $conversation->getId()]);
        }

        if (mb_strlen($body) > 2000) {
            $this->addFlash('warning', 'Messages are limited to 2000 characters.');

            return $this->redirectToRoute('app_message_thread', ['id' => $conversation->getId()]);
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($user)
            ->setBody($body);

        $conversation->touchLastMessageAt();
        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('app_message_thread', ['id' => $conversation->getId()]);
    }

    private function assertParticipant(Conversation $conversation): void
    {
        if (!$conversation->includes($this->getUser()) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not part of this conversation.');
        }
    }
}
