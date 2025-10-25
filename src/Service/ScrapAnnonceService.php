<?php

namespace App\Service;

use App\Entity\Annonce;
use App\Entity\Log;
use App\Entity\LogAction;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class ScrapAnnonceService
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function addPreAnnonce($id, $url) {
        $annonceRepo = $this->em->getRepository(Annonce::class);

        $existingById = $annonceRepo->findOneBy(['id' => $id]);
        $existingByUrl = $annonceRepo->findOneBy(['lien' => $url]);

        if (!$existingById && !$existingByUrl) {
            $annonce = new Annonce();
            $annonce->setId($id);
            $annonce->setLien($url);
            $annonce->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($annonce);

            return true;

        } else {
            return "Déjà existante : $url";
        }

        return false;
    }

}

