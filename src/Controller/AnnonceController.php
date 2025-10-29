<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnonceController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserService $userService;

    public function __construct(EntityManagerInterface $em, UserService $userService)
    {
        $this->em = $em;
        $this->userService = $userService;
    }

    #[Route('/', name: 'main')]
    public function index(): Response
    {
        if (!$this->userService->getCurrentUser()) {
            return $this->redirectToRoute('app_login');
        }

        //$annonces = $this->em->getRepository(Annonce::class)->findBy(['isActive'=>true], ['createdAt'=>'ASC']);
        $annonces = $this->em->getRepository(Annonce::class)->findBy(['isActive'=>true, 'isSwiped'=>true], ['createdAt'=>'ASC']);
        return $this->render('home.html.twig', [
            'annonces' => $annonces,
        ]);
    }
}
