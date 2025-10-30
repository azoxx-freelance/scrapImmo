<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use function PHPUnit\Framework\isFloat;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Annonce
{
    public static $sourceArray = [
        'SL'    => 'SL',    // SeLoger
        'LBC'   => 'LBC',   // LeBonCoin
    ];

    public static $motifRefus = [
        'Cuisine étroite'       => 'CUISINE_ETROITE',
        'Salle de bain étroite' => 'SDB_ETROITE',
        'Cuisine vetuste'       => 'CUISINE_VETUSTE',
        'Salle de bain vetuste' => 'SDB_VETUSTE',
        'rez-de-chaussée'       => 'RDC',
        'Cuisine dans le salon' => 'CUISINE_SALON',
        'Vetuste'               => 'VETUSTE',
        'Bail commercial'       => 'BAIL',
        'Petit'                 => 'SMALL',
        'Pas ouf'               => 'BOF',
    ];

    public static $motifSelection = [
        'Belle vue'         => 'VUE',
        'Mention honorable' => 'HONOR',
        'Bon feeling'       => 'FEELING',
        'Intérieur sympas'  => 'INTERIOR',
        'Bon agencement'    => 'AGENCEMENT',
    ];

    public static function getMotifRefus(): array
    {
        return self::$motifRefus;
    }

    public static function getMotifSelection(): array
    {
        return self::$motifSelection;
    }

    public static function getAllMotifsFlip(): array
    {
        return array_flip(array_merge(self::$motifSelection,self::$motifRefus));
    }

    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $source = null;

    #[ORM\Column(type: 'text')]
    private ?string $lien = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateConstruction = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chauffageType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chauffageSource = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeVente = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?bool $notaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $prix = null;

    #[ORM\Column(nullable: true)]
    private ?int $prixSurface = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $annonceur = null;

    #[ORM\Column(nullable: true)]
    private ?int $chargeCopro = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $surface = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbPiece = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbChambre = null;

    #[ORM\Column(nullable: true)]
    private ?int $etage = null;

    #[ORM\Column(nullable: true)]
    private ?int $etageMax = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $quartier = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $dpe = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $ges = null;

    #[ORM\Column]
    private bool $isActive = false;

    #[ORM\Column]
    private bool $isSwiped = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $swipedMotifs = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'annonce', targetEntity: Caracteristique::class, cascade: ['persist', 'remove'])]
    private Collection $caracteristiques;

    #[ORM\OneToMany(mappedBy: 'annonce', targetEntity: Commentaire::class, cascade: ['persist', 'remove'])]
    private Collection $commentaires;

    #[ORM\OneToMany(mappedBy: 'annonce', targetEntity: Photo::class, cascade: ['persist', 'remove'])]
    private Collection $photos;

    public function __construct()
    {
        $this->caracteristiques = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->photos = new ArrayCollection();
    }

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
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * @param string|null $source
     */
    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    /**
     * @return string|null
     */
    public function getLien(): ?string
    {
        return $this->lien;
    }

    /**
     * @param string|null $lien
     */
    public function setLien(?string $lien): void
    {
        $this->lien = $lien;
    }

    /**
     * @return string|null
     */
    public function getTitre(): ?string
    {
        return $this->titre;
    }

    /**
     * @param string|null $titre
     */
    public function setTitre(?string $titre): void
    {
        $this->titre = $titre;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getDateConstruction(): ?\DateTimeInterface
    {
        return $this->dateConstruction;
    }

    /**
     * @param \DateTimeInterface|null $dateConstruction
     */
    public function setDateConstruction($dateConstruction): void
    {
        if ($dateConstruction === null) {
            $this->dateConstruction = null;
            return;
        }

        if (!$dateConstruction instanceof \DateTimeInterface) {
            try {
                $dateConstruction = new \DateTime($dateConstruction);
            } catch (\Exception $e) {
                $this->dateConstruction = null;
                return;
            }
        }

        $this->dateConstruction = $dateConstruction;
    }

    /**
     * @return string|null
     */
    public function getChauffageType(): ?string
    {
        return $this->chauffageType;
    }

    /**
     * @param string|null $chauffageType
     */
    public function setChauffageType(?string $chauffageType): void
    {
        $this->chauffageType = $chauffageType;
    }

    /**
     * @return string|null
     */
    public function getChauffageSource(): ?string
    {
        return $this->chauffageSource;
    }

    /**
     * @param string|null $chauffageSource
     */
    public function setChauffageSource(?string $chauffageSource): void
    {
        $this->chauffageSource = $chauffageSource;
    }

    /**
     * @return string|null
     */
    public function getEtat(): ?string
    {
        return $this->etat;
    }

    /**
     * @param string|null $etat
     */
    public function setEtat(?string $etat): void
    {
        $this->etat = $etat;
    }

    /**
     * @return string|null
     */
    public function getTypeVente(): ?string
    {
        return $this->typeVente;
    }

    /**
     * @param string|null $typeVente
     */
    public function setTypeVente(?string $typeVente): void
    {
        $this->typeVente = $typeVente;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return bool|null
     */
    public function getNotaire(): ?bool
    {
        return $this->notaire;
    }

    /**
     * @param bool|null $notaire
     */
    public function setNotaire(?bool $notaire): void
    {
        $this->notaire = $notaire;
    }

    /**
     * @return int|null
     */
    public function getPrix(): ?int
    {
        return $this->prix;
    }

    /**
     * @param int|null $prix
     */
    public function setPrix(?int $prix): void
    {
        $this->prix = $prix;
    }

    /**
     * @return int|null
     */
    public function getPrixSurface(): ?int
    {
        return $this->prixSurface;
    }

    /**
     * @param int|null $prixSurface
     */
    public function setPrixSurface(?int $prixSurface): void
    {
        $this->prixSurface = $prixSurface;
    }

    /**
     * @return string|null
     */
    public function getAnnonceur(): ?string
    {
        return $this->annonceur;
    }

    /**
     * @param string|null $annonceur
     */
    public function setAnnonceur(?string $annonceur): void
    {
        $this->annonceur = $annonceur;
    }

    /**
     * @return int|null
     */
    public function getChargeCopro(): ?int
    {
        return $this->chargeCopro;
    }

    /**
     * @param int|null $chargeCopro
     */
    public function setChargeCopro(?int $chargeCopro): void
    {
        $this->chargeCopro = $chargeCopro;
    }

    /**
     * @return float|null
     */
    public function getSurface(): ?float
    {
        return $this->surface;
    }

    /**
     * @param float|null $surface
     */
    public function setSurface($surface): void
    {
        if ($surface === null) {
            $this->surface = null;
            return;
        }

        if(!isFloat($surface)) {
            try {
                $surface = floatval($surface);
            } catch (\Exception $e) {
                $this->surface = null;
                return;
            }
        }

        $this->surface = $surface;
    }

    /**
     * @return int|null
     */
    public function getNbPiece(): ?int
    {
        return $this->nbPiece;
    }

    /**
     * @param int|null $nbPiece
     */
    public function setNbPiece(?int $nbPiece): void
    {
        $this->nbPiece = $nbPiece;
    }

    /**
     * @return int|null
     */
    public function getNbChambre(): ?int
    {
        return $this->nbChambre;
    }

    /**
     * @param int|null $nbChambre
     */
    public function setNbChambre(?int $nbChambre): void
    {
        $this->nbChambre = $nbChambre;
    }

    /**
     * @return int|null
     */
    public function getEtage(): ?int
    {
        return $this->etage;
    }

    /**
     * @param int|null $etage
     */
    public function setEtage(?int $etage): void
    {
        $this->etage = $etage;
    }

    /**
     * @return int|null
     */
    public function getEtageMax(): ?int
    {
        return $this->etageMax;
    }

    /**
     * @param int|null $etageMax
     */
    public function setEtageMax(?int $etageMax): void
    {
        $this->etageMax = $etageMax;
    }

    /**
     * @return string|null
     */
    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    /**
     * @param string|null $adresse
     */
    public function setAdresse(?string $adresse): void
    {
        $this->adresse = $adresse;
    }

    /**
     * @return string|null
     */
    public function getVille(): ?string
    {
        return $this->ville;
    }

    /**
     * @param string|null $ville
     */
    public function setVille(?string $ville): void
    {
        $this->ville = $ville;
    }

    /**
     * @return string|null
     */
    public function getQuartier(): ?string
    {
        return $this->quartier;
    }

    /**
     * @param string|null $quartier
     */
    public function setQuartier(?string $quartier): void
    {
        $this->quartier = $quartier;
    }

    /**
     * @return string|null
     */
    public function getDpe(): ?string
    {
        return $this->dpe;
    }

    /**
     * @param string|null $dpe
     */
    public function setDpe(?string $dpe): void
    {
        $this->dpe = $dpe;
    }

    /**
     * @return string|null
     */
    public function getGes(): ?string
    {
        return $this->ges;
    }

    /**
     * @param string|null $ges
     */
    public function setGes(?string $ges): void
    {
        $this->ges = $ges;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return bool
     */
    public function isSwiped(): bool
    {
        return $this->isSwiped;
    }

    /**
     * @param bool $isSwiped
     */
    public function setIsSwiped(bool $isSwiped): void
    {
        $this->isSwiped = $isSwiped;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getSwipedMotifs(): ?string
    {
        return $this->swipedMotifs;
    }

    public function setSwipedMotifs(?string $swipedMotifs): void
    {
        $this->swipedMotifs = $swipedMotifs;
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

    /**
     * @return ArrayCollection|Collection
     */
    public function getCaracteristiques(): ArrayCollection|Collection
    {
        return $this->caracteristiques;
    }

    /**
     * @param ArrayCollection|Collection $caracteristiques
     */
    public function setCaracteristiques(ArrayCollection|Collection $caracteristiques): void
    {
        $this->caracteristiques = $caracteristiques;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getCommentaires(): ArrayCollection|Collection
    {
        return $this->commentaires;
    }

    /**
     * @param ArrayCollection|Collection $commentaires
     */
    public function setCommentaires(ArrayCollection|Collection $commentaires): void
    {
        $this->commentaires = $commentaires;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getPhotos(): ArrayCollection|Collection
    {
        return $this->photos;
    }

    /**
     * @param ArrayCollection|Collection $photos
     */
    public function setPhotos(ArrayCollection|Collection $photos): void
    {
        $this->photos = $photos;
    }

    public function returnNull()
    {
        return;
    }

}
