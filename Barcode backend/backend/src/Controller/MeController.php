<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Unauthenticated'], 401);
        }

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getUserIdentifier(), // ou getEmail() selon ton User
            'roles' => $user->getRoles(),
        ]);
    }


    #[Route('/me', name: 'me_delete', methods: ['DELETE'])]
    public function deleteMe(EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $em->remove($user);
        $em->flush();

        return new Response('', Response::HTTP_NO_CONTENT); // ✅ 204 vide
    }
}
