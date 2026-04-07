<?php

namespace App\Entity;

use App\Repository\CompetitionRegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitionRegistrationRepository::class)]
#[ORM\UniqueConstraint(columns: ['athlete_id', 'competition_id'])]
class CompetitionRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Athlete::class, inversedBy: 'competitionRegistrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Athlete $athlete = null;

    #[ORM\ManyToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Competition $competition = null;

    #[ORM\Column(type: Types::JSON)]
    private array $disciplines = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $registeredAt;

    #[ORM\Column(type: 'boolean')]
    private bool $ffaRegistered = false;

    public function __construct()
    {
        $this->registeredAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAthlete(): ?Athlete { return $this->athlete; }
    public function setAthlete(?Athlete $athlete): static { $this->athlete = $athlete; return $this; }

    public function getCompetition(): ?Competition { return $this->competition; }
    public function setCompetition(?Competition $competition): static { $this->competition = $competition; return $this; }

    public function getDisciplines(): array { return $this->disciplines; }
    public function setDisciplines(array $disciplines): static { $this->disciplines = $disciplines; return $this; }

    public function getRegisteredAt(): \DateTimeInterface { return $this->registeredAt; }

    public function isFfaRegistered(): bool { return $this->ffaRegistered; }
    public function setFfaRegistered(bool $ffaRegistered): static { $this->ffaRegistered = $ffaRegistered; return $this; }
}
