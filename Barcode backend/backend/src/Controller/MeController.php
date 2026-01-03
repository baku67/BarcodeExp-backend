<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
            'isVerified' => $user->isVerified(),
            'preferences' => [
                'theme' => $user->getPrefTheme(),
                'lang' => $user->getPrefLang(),
                'frigo_layout' => $user->getPrefFrigoLayout(),
            ],
            'preferencesUpdatedAt' => $user->getPreferencesUpdatedAt()?->getTimestamp(),
        ]);
    }

    #[Route('/me/preferences', methods: ['PATCH'])]
    public function patchPreferences(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) return new Response('', 401);

        $data = json_decode($request->getContent() ?: '{}', true) ?? [];

        if (isset($data['theme'])) {
            $theme = (string) $data['theme'];
            if (!in_array($theme, ['system','light','dark'], true)) {
                return $this->json(['message' => 'Invalid theme'], 400);
            }
            $user->setPref('theme', $theme);
        }

        if (isset($data['lang'])) {
            $lang = (string) $data['lang'];
            if ($lang === '' || strlen($lang) > 10) {
                return $this->json(['message' => 'Invalid lang'], 400);
            }
            $user->setPref('lang', $lang);
        }

        if (isset($data['frigo_layout'])) {
            $layout = (string) $data['frigo_layout'];
            if (!in_array($layout, ['list','design'], true)) {
                return $this->json(['message' => 'Invalid frigo_layout'], 400);
            }
            $user->setPref('frigo_layout', $layout);
        }

        $em->flush();

        return $this->json([
            'preferences' => $user->getPreferences(),
            'preferencesUpdatedAt' => $user->getPreferencesUpdatedAt()?->getTimestamp(),
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
