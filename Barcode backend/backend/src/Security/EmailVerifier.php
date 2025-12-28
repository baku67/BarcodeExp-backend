<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager
    ) {}

    public function sendEmailConfirmation(string $verifyEmailRouteName, User $user, TemplatedEmail $email, array $extraQueryParams = []): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            $user->getUserIdentifier(), // ou getEmail()
            $extraQueryParams
        );

        $signedUrl = $signatureComponents->getSignedUrl();

        // ✅ Durée de validité en minutes (basée sur le paramètre "expires" du lien)
        $expiresInMinutes = null;
        $parts = parse_url($signedUrl);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['expires']) && ctype_digit((string) $query['expires'])) {
                $secondsLeft = ((int) $query['expires']) - time();
                if ($secondsLeft > 0) {
                    $expiresInMinutes = (int) ceil($secondsLeft / 60);
                }
            }
        }

        $context = $email->getContext();
        $context['signedUrl'] = $signedUrl;
        $context['expiresInMinutes'] = $expiresInMinutes; 
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();
        $context['appName'] = 'FrigoZen';
        $context['logoUrl'] = rtrim($_ENV['APP_URL'] ?? '', '/') . '/assets/branding/frigozen_icon.png';

        $email->context($context);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getId(),
            $user->getUserIdentifier() // ou getEmail()
        );

        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
