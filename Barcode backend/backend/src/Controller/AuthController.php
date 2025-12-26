<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        JWTTokenManagerInterface $jwt
    ): JsonResponse {
        $data = $request->toArray();

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $confirmPassword = (string)($data['confirmPassword'] ?? ''); // IMPORTANT: nom exact côté Android 

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

        $em->persist($user);
        $em->flush();

        $token = $jwt->create($user);

        // Doit matcher: RegisterResponse(val id: String, val token: String) 
        return $this->json([
            'id' => (string) $user->getId(),
            'token' => $token,
        ], 201);
    }
}
