<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class FeedbackController extends AbstractController
{
    #[Route('/feedback/report', name: 'app_feedback_report', methods: ['POST'])]
    public function report(Request $request, MailerInterface $mailer): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $type        = $request->request->get('type', 'bug');
        $description = trim($request->request->get('description', ''));
        $page        = $request->request->get('page', 'inconnue');

        if (strlen($description) < 5) {
            return $this->json(['success' => false, 'message' => 'Description trop courte.'], 400);
        }

        $user     = $this->getUser();
        $userName = $user->getFirstName() . ' ' . $user->getLastName();
        $userEmail = $user->getEmail();

        $label = $type === 'suggestion' ? '💡 Suggestion' : '🐛 Bug';

        $email = (new Email())
            ->from($_ENV['FEEDBACK_FROM_EMAIL'] ?? 'noreply@trackflow.app')
            ->to($_ENV['FEEDBACK_TO_EMAIL'] ?? 'bugs@trackflow.app')
            ->replyTo($userEmail)
            ->subject("[Trackflow] {$label} — {$userName}")
            ->html(
                "<h2>{$label} signalé par {$userName} ({$userEmail})</h2>" .
                "<p><strong>Page :</strong> " . htmlspecialchars($page) . "</p>" .
                "<hr>" .
                "<p>" . nl2br(htmlspecialchars($description)) . "</p>"
            );

        $mailer->send($email);

        return $this->json(['success' => true]);
    }
}
