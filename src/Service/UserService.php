<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UserService
{
    private EntityManagerInterface $em;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    public function getCurrentUser(): ?User
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $pseudo = $request->cookies->get('user_pseudo');
        if (!$pseudo) {
            return null;
        }

        return $this->em->getRepository(User::class)->findOneBy(['pseudo' => $pseudo]);
    }
}