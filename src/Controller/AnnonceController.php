<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/annonce/detail/{id}', name: 'annonce_detail')]
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

    #[Route('/annonce/swipeNext', name: 'annonce_swipe_next')]
    public function swipeNext(): Response
    {
        if (!$this->userService->getCurrentUser()) {
            return $this->redirectToRoute('app_login');
        }

        $annonces = $this->em->getRepository(Annonce::class)->findBy(['isSwiped'=>false], ['createdAt'=>'ASC', 'updatedAt'=>'ASC']);
        $annonceId = $annonces[0]->getId() ?? null;

        if($annonceId) {
            return $this->redirectToRoute('annonce_detail', ['id' => $annonceId]);
        } else {
            return $this->redirectToRoute('main');
        }
    }

    #[Route('/annonce/swipe/{id}/', name: 'annonce_swipe_js', methods: ['POST'])]
    #[Route('/annonce/swipe/{id}/{action}', name: 'annonce_swipe', methods: ['POST'], requirements: ['action' => 'accept|reject'])]
    public function swipe(Request $request, string $id, string $action = ''): Response
    {
        if (!$this->userService->getCurrentUser()) {
            return new Response('Non autorisé', 401);
        }

        $annonce = $this->em->getRepository(Annonce::class)->find($id);
        if (!$annonce) {
            return new Response('Annonce non trouvée', 404);
        }

        $motifs = $request->query->get('motifs', '');

        $annonce->setIsSwiped(true);
        $annonce->setSwipedMotifs($motifs);

        if ($action === 'reject') {
            $annonce->setIsActive(false);
        }

        $this->em->flush();

        return new Response('OK');
    }

    #[Route('/annonce/modal/{id}', name: 'annonce_modal')]
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
