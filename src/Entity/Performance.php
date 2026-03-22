<?php

namespace App\Entity;

use App\Repository\PerformanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PerformanceRepository::class)]
class Performance
{
    public const DISCIPLINES = [
        '60m' => '60m',
        '100m' => '100m',
        '200m' => '200m',
        '400m' => '400m',
        '800m' => '800m',
        '1500m' => '1500m',
        '3000m' => '3000m',
        '5000m' => '5000m',
        '10000m' => '10000m',
        'Semi-marathon' => 'semi-marathon',
        'Marathon' => 'marathon',
        '60m haies' => '60m-haies',
        '110m haies' => '110m-haies',
        '400m haies' => '400m-haies',
        'Saut en longueur' => 'longueur',
        'Saut en hauteur' => 'hauteur',
        'Triple saut' => 'triple',
        'Saut à la perche' => 'perche',
        'Lancer du poids' => 'poids',
        'Lancer du disque' => 'disque',
        'Lancer du javelot' => 'javelot',
        'Lancer du marteau' => 'marteau',
        'Décathlon' => 'decathlon',
        'Heptathlon' => 'heptathlon',
        'Autre' => 'autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Athlete::class, inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Athlete $athlete = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Session $session = null;

    #[ORM\Column(length: 50)]
    private ?string $discipline = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private ?string $value = null;

    #[ORM\Column(length: 20)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $recordedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isPersonalBest = false;

    #[ORM\Column(nullable: true)]
    private ?bool $isCompetition = false;

    public function __construct()
    {
        $this->recordedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAthlete(): ?Athlete { return $this->athlete; }
    public function setAthlete(?Athlete $athlete): static { $this->athlete = $athlete; return $this; }

    public function getSession(): ?Session { return $this->session; }
    public function setSession(?Session $session): static { $this->session = $session; return $this; }

    public function getDiscipline(): ?string { return $this->discipline; }
    public function setDiscipline(string $discipline): static { $this->discipline = $discipline; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(string $value): static { $this->value = $value; return $this; }

    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(string $unit): static { $this->unit = $unit; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getRecordedAt(): ?\DateTimeInterface { return $this->recordedAt; }
    public function setRecordedAt(\DateTimeInterface $recordedAt): static { $this->recordedAt = $recordedAt; return $this; }

    public function getIsPersonalBest(): ?bool { return $this->isPersonalBest; }
    public function setIsPersonalBest(?bool $isPersonalBest): static { $this->isPersonalBest = $isPersonalBest; return $this; }

    public function getIsCompetition(): ?bool { return $this->isCompetition; }
    public function setIsCompetition(?bool $isCompetition): static { $this->isCompetition = $isCompetition; return $this; }

    public function getFormattedValue(): string
    {
        if (in_array($this->unit, ['s', 'min:s'])) {
            $secs = (float) $this->value;
            if ($secs >= 60) {
                $min = floor($secs / 60);
                $sec = $secs - ($min * 60);
                return sprintf('%d:%05.2f', $min, $sec);
            }
            return number_format($secs, 2) . ' s';
        }
        return $this->value . ' ' . $this->unit;
    }
}
