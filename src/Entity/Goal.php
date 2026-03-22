<?php

namespace App\Entity;

use App\Repository\GoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoalRepository::class)]
class Goal
{
    public const STATUSES = [
        'En cours' => 'in_progress',
        'Atteint' => 'achieved',
        'Abandonné' => 'abandoned',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Athlete::class, inversedBy: 'goals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Athlete $athlete = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $discipline = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $targetValue = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'in_progress';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = 'in_progress';
    }

    public function getId(): ?int { return $this->id; }

    public function getAthlete(): ?Athlete { return $this->athlete; }
    public function setAthlete(?Athlete $athlete): static { $this->athlete = $athlete; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDiscipline(): ?string { return $this->discipline; }
    public function setDiscipline(?string $discipline): static { $this->discipline = $discipline; return $this; }

    public function getTargetValue(): ?string { return $this->targetValue; }
    public function setTargetValue(?string $targetValue): static { $this->targetValue = $targetValue; return $this; }

    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(?string $unit): static { $this->unit = $unit; return $this; }

    public function getDeadline(): ?\DateTimeInterface { return $this->deadline; }
    public function setDeadline(?\DateTimeInterface $deadline): static { $this->deadline = $deadline; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function isAchieved(): bool { return $this->status === 'achieved'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }

    public function isOverdue(): bool
    {
        if (!$this->deadline || $this->status !== 'in_progress') return false;
        return $this->deadline < new \DateTime();
    }
}
