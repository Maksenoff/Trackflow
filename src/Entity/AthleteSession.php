<?php

namespace App\Entity;

use App\Repository\AthleteSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AthleteSessionRepository::class)]
#[ORM\UniqueConstraint(name: 'athlete_session_unique', columns: ['athlete_id', 'session_id'])]
class AthleteSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Athlete::class, inversedBy: 'athleteSessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Athlete $athlete = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'athleteSessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(nullable: true)]
    private ?int $difficulty = null; // 0-10

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $loggedAt = null;

    public function __construct()
    {
        $this->loggedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAthlete(): ?Athlete { return $this->athlete; }
    public function setAthlete(?Athlete $athlete): static { $this->athlete = $athlete; return $this; }

    public function getSession(): ?Session { return $this->session; }
    public function setSession(?Session $session): static { $this->session = $session; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function getDifficulty(): ?int { return $this->difficulty; }
    public function setDifficulty(?int $difficulty): static { $this->difficulty = $difficulty; return $this; }

    public function getLoggedAt(): ?\DateTimeInterface { return $this->loggedAt; }
}
