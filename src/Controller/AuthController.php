<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $pseudo = $request->request->get('pseudo');
            $password = $request->request->get('password');

            $user = $this->em->getRepository(User::class)->findOneBy(['pseudo' => $pseudo]);

            if ($user && $user->verifyPassword($password)) {
                $response = $this->redirectToRoute('main');
                $response->headers->setCookie(Cookie::create('user_pseudo', $pseudo, time() + (30 * 24 * 60 * 60))); // 30 jours
                return $response;
            }

            $this->addFlash('error', 'Pseudo ou mot de passe incorrect');
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $pseudo = $request->request->get('pseudo');
            $password = $request->request->get('password');

            if ($this->em->getRepository(User::class)->findOneBy(['pseudo' => $pseudo])) {
                $this->addFlash('error', 'Ce pseudo est déjà utilisé');
            } else {
                $user = new User();
                $user->setPseudo($pseudo);
                $user->setPassword($password);

                $this->em->persist($user);
                $this->em->flush();

                $response = $this->redirectToRoute('app_login');
                $response->headers->setCookie(Cookie::create('user_pseudo', $pseudo, time() + (30 * 24 * 60 * 60)));
                return $response;
            }
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
        $response = $this->redirectToRoute('main');
        $response->headers->clearCookie('user_pseudo');
        return $response;
    }
}
