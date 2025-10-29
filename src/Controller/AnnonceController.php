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

    #[Route('/annonce/{id}', name: 'annonce_detail')]
    public function detail(string $id): Response
    {
        if (!$this->userService->getCurrentUser()) {
            return $this->redirectToRoute('app_login');
        }

        $annonce = $this->em->getRepository(Annonce::class)->find($id);

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce non trouvée');
        }

        return $this->render('annonce_detail.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    #[Route('/annonce-modal/{id}', name: 'annonce_modal')]
    public function modal(string $id): Response
    {
        if (!$this->userService->getCurrentUser()) {
            return $this->redirectToRoute('app_login');
        }

        $annonce = $this->em->getRepository(Annonce::class)->find($id);

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce non trouvée');
        }

        return $this->render('_annonce_detail.html.twig', [
            'annonce' => $annonce,
        ]);
    }
}
