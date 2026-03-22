<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(targetEntity: TrainingType::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TrainingType $trainingType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\OneToMany(targetEntity: AthleteSession::class, mappedBy: 'session', cascade: ['remove'])]
    private Collection $athleteSessions;

    public function __construct()
    {
        $this->athleteSessions = new ArrayCollection();
        $this->date = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }

    public function getTrainingType(): ?TrainingType { return $this->trainingType; }
    public function setTrainingType(?TrainingType $trainingType): static { $this->trainingType = $trainingType; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getDurationMinutes(): ?int { return $this->durationMinutes; }
    public function setDurationMinutes(?int $durationMinutes): static { $this->durationMinutes = $durationMinutes; return $this; }

    public function getAthleteSessions(): Collection { return $this->athleteSessions; }

    public function isPast(): bool
    {
        return $this->date < new \DateTime('today');
    }

    public function getColorBg(): string
    {
        return $this->trainingType?->getColor() ?? '#6366f1';
    }

    public function getTypeLabel(): string
    {
        return $this->trainingType?->getName() ?? '—';
    }
}
