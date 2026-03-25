<?php

namespace App\Entity;

use App\Repository\AthleteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AthleteRepository::class)]
class Athlete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(name: 'discipline', type: Types::JSON)]
    private array $disciplines = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ffaProfileUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(targetEntity: Performance::class, mappedBy: 'athlete', cascade: ['remove'])]
    #[ORM\OrderBy(['recordedAt' => 'DESC'])]
    private Collection $performances;

    #[ORM\OneToMany(targetEntity: Goal::class, mappedBy: 'athlete', cascade: ['remove'])]
    private Collection $goals;

    #[ORM\OneToMany(targetEntity: AthleteSession::class, mappedBy: 'athlete', cascade: ['remove'])]
    #[ORM\OrderBy(['loggedAt' => 'DESC'])]
    private Collection $athleteSessions;

    #[ORM\OneToMany(targetEntity: AthleteVideo::class, mappedBy: 'athlete', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $videos;

    public function __construct()
    {
        $this->performances    = new ArrayCollection();
        $this->goals           = new ArrayCollection();
        $this->athleteSessions = new ArrayCollection();
        $this->videos          = new ArrayCollection();
        $this->createdAt       = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = mb_strtoupper($lastName, 'UTF-8'); return $this; }

    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }

    public function getBirthDate(): ?\DateTimeInterface { return $this->birthDate; }
    public function setBirthDate(?\DateTimeInterface $birthDate): static { $this->birthDate = $birthDate; return $this; }

    public function getAge(): ?int
    {
        if (!$this->birthDate) return null;
        return (new \DateTime())->diff($this->birthDate)->y;
    }

    public function getGender(): ?string { return $this->gender; }
    public function setGender(?string $gender): static { $this->gender = $gender; return $this; }

    public function getDiscipline(): string { return implode(', ', $this->disciplines); }
    public function getDisciplines(): array { return $this->disciplines; }
    public function setDisciplines(array $disciplines): static { $this->disciplines = $disciplines; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getFfaProfileUrl(): ?string { return $this->ffaProfileUrl; }
    public function setFfaProfileUrl(?string $ffaProfileUrl): static { $this->ffaProfileUrl = $ffaProfileUrl; return $this; }

    public function getLastSyncedAt(): ?\DateTimeImmutable { return $this->lastSyncedAt; }
    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static { $this->lastSyncedAt = $lastSyncedAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getPerformances(): Collection { return $this->performances; }
    public function getGoals(): Collection { return $this->goals; }
    public function getAthleteSessions(): Collection { return $this->athleteSessions; }
    public function getVideos(): Collection { return $this->videos; }
}
