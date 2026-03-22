<?php

namespace App\Entity;

use App\Repository\AthleteVideoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AthleteVideoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AthleteVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Athlete $athlete = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discipline = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getAthlete(): ?Athlete { return $this->athlete; }
    public function setAthlete(?Athlete $athlete): static { $this->athlete = $athlete; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDiscipline(): ?string { return $this->discipline; }
    public function setDiscipline(?string $discipline): static { $this->discipline = $discipline; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
