<?php

namespace App\Controller;

use App\Entity\Annonce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnonceController extends AbstractController
{
    private EntityManagerInterface $em;


    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/', name: 'main')]
    public function index(): Response
    {
        $annonces = $this->em->getRepository(Annonce::class)->findBy(['isActive'=>true], ['createdAt'=>'ASC']);
        return $this->render('home.html.twig', [
            'annonces' => $annonces,
        ]);
    }
}
