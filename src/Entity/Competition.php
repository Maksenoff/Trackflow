<?php

namespace App\Entity;

use App\Repository\CompetitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitionRepository::class)]
class Competition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(targetEntity: CompetitionType::class, inversedBy: 'competitions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?CompetitionType $competitionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentFilename = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $websiteUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->date      = new \DateTime();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $this->location = $location; return $this; }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }

    public function getCompetitionType(): ?CompetitionType { return $this->competitionType; }
    public function setCompetitionType(?CompetitionType $competitionType): static { $this->competitionType = $competitionType; return $this; }

    public function getDocumentFilename(): ?string { return $this->documentFilename; }
    public function setDocumentFilename(?string $documentFilename): static { $this->documentFilename = $documentFilename; return $this; }

    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function setWebsiteUrl(?string $websiteUrl): static { $this->websiteUrl = $websiteUrl; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function isPast(): bool
    {
        return $this->date < new \DateTime('today');
    }

    public function getColorBg(): string
    {
        return $this->competitionType?->getColor() ?? '#f59e0b';
    }

    public function getTypeLabel(): string
    {
        return $this->competitionType?->getName() ?? '—';
    }
}
