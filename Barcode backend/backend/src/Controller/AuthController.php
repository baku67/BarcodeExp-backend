<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[Route('/auth')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt
    ): JsonResponse {
        $data = $request->toArray();

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'email et password requis'], 400);
        }

        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            // 401 sans détail (évite de révéler si l’email existe)
            return $this->json(['message' => 'Identifiants invalides'], 401);
        }

        if (!$hasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Identifiants invalides'], 401);
        }

        $token = $jwt->create($user);

        // Doit matcher: LoginResponse(val token: String)
        return $this->json(['token' => $token], 200);
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt,
        EmailVerifier $emailVerifier
    ): JsonResponse {
        $data = $request->toArray();

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $confirmPassword = (string)($data['confirmPassword'] ?? '');

        if ($email === '' || $password === '' || $confirmPassword === '') {
            return $this->json(['message' => 'email, password, confirmPassword requis'], 400);
        }

        if ($password !== $confirmPassword) {
            return $this->json(['message' => 'confirmPassword ne correspond pas'], 400);
        }

        if ($users->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'Email déjà utilisé'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, $password));

        $user->setIsVerified(false);

        $em->persist($user);
        $em->flush();

        // ✅ Envoi du mail (validation anonyme: on ajoute ?id=...)
        $emailVerifier->sendEmailConfirmation(
            'auth_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('no-reply@ton-domaine.com', 'FrigoZen'))
                ->to($user->getEmail())
                ->subject('Confirme ton adresse email')
                ->htmlTemplate('emails/verify_email.html.twig'),
            ['id' => (string) $user->getId()]
        );

        $token = $jwt->create($user);

        // Doit matcher: RegisterResponse(val id: String, val token: String) 
        return $this->json([
            'id' => (string) $user->getId(),
            'token' => $token,
        ], 201);
    }


    #[Route('/verify/resend', name: 'auth_verify_resend', methods: ['POST'])]
    public function resendVerifyEmail(
        EmailVerifier $emailVerifier, 
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isVerified()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Rate limiting custom (+ RateLimiterFactory $verifyResendByUser + RateLimiterFactory $verifyResendByIp)
        $now = new \DateTimeImmutable();
        $last = $user->getVerificationEmailLastSentAt();
        if ($last && $last > $now->sub(new \DateInterval('PT60S'))) {
            // soit 204 (silencieux), soit 429 (UX)
            return new Response('', Response::HTTP_TOO_MANY_REQUESTS);
        }
        $user->setVerificationEmailLastSentAt($now);
        $em->flush();

        $emailVerifier->sendEmailConfirmation(
            'auth_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('no-reply@ton-domaine.com', 'FrigoZen'))
                ->to($user->getUserIdentifier())
                ->subject('Confirme ton adresse email - FrigoZen')
                ->htmlTemplate('emails/verify_email.html.twig'),
            ['id' => (string) $user->getId()]
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }


    #[Route('/verify/email', name: 'auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, UserRepository $users, EmailVerifier $emailVerifier): Response
    {
        $id = $request->query->get('id');
        if (!$id) {
            return new Response('Missing id', 400);
        }

        $user = $users->find($id);
        if (!$user) {
            return new Response('User not found', 404);
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $e) {
            return new Response('Lien invalide ou expiré', 400);
        }

        $ua = (string) $request->headers->get('User-Agent', '');
        $isMobile = (bool) preg_match('/Android|iPhone|iPad|iPod/i', $ua);

        $deepLink = sprintf('frigozen://email-verified?id=%s', $user->getId());
        $fallbackUrl = 'http://localhost:8080'; // ou une page “télécharger l’app”

        return $this->render('pages/email_verified.html.twig', [
            'deepLink' => $deepLink,
            'fallbackUrl' => $fallbackUrl,
            'isMobile' => $isMobile,
        ]);
    }
}
