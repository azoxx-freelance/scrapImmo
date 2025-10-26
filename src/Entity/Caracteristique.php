<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Caracteristique
{

    public static $caracteristiquesCategories = [
        'meubl'         => 'MEUBLE',
        'ascenseur'     => 'ASCENSEUR',
        'balcon'        => 'BALCON',
        'jardin'        => 'JARTDIN',
        'terrasse'      => 'TERASSE',
        'cave'          => 'CAVE',
        'box'           => 'BOX',
        'tage'          => 'ETAGE',
        'exposition'    => 'EXPOSITION',

        'parking'       => 'PARKING',
        'stationnement' => 'PARKING',
        'garage'        => 'PARKING',

        'chambre'       => 'PIECES',
        'salle'         => 'PIECES',
        'wc'            => 'PIECES',
        'cuisine'       => 'PIECES',

        'type de chauffage' => 'setChauffageType',

        'source'        => 'setChauffageSource',
        'nergie'        => 'setChauffageSource',

        'année'         => 'setDateConstruction',
        'construction'  => 'setDateConstruction',

        'état'          => 'setEtat',
        'etat'          => 'setEtat',
        'tat'           => 'setEtat',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'caracteristiques')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Annonce $annonce = null;

    #[ORM\Column(length: 100)]
    private string $code;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Annonce|null
     */
    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    /**
     * @param Annonce|null $annonce
     */
    public function setAnnonce(?Annonce $annonce): void
    {
        $this->annonce = $annonce;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param string|null $value
     */
    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeInterface $createdAt
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTimeInterface $updatedAt
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }


}
