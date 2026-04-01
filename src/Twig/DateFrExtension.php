<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFrExtension extends AbstractExtension
{
    private const MONTHS_LONG = [
        'January'   => 'janvier',   'February' => 'février',  'March'    => 'mars',
        'April'     => 'avril',     'May'      => 'mai',       'June'     => 'juin',
        'July'      => 'juillet',   'August'   => 'août',      'September'=> 'septembre',
        'October'   => 'octobre',   'November' => 'novembre',  'December' => 'décembre',
    ];

    private const MONTHS_SHORT = [
        'Jan' => 'jan', 'Feb' => 'fév', 'Mar' => 'mar', 'Apr' => 'avr',
        'May' => 'mai', 'Jun' => 'juin','Jul' => 'juil','Aug' => 'août',
        'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'déc',
    ];

    private const DAYS_LONG = [
        'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', $this->dateFr(...)),
        ];
    }

    public function dateFr(\DateTimeInterface|string|null $date, string $format): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $result = $date->format($format);

        // Remplace les noms de mois longs (F)
        $result = strtr($result, self::MONTHS_LONG);

        // Remplace les noms de mois courts (M)
        $result = strtr($result, self::MONTHS_SHORT);

        // Remplace les noms de jours (l)
        $result = strtr($result, self::DAYS_LONG);

        return $result;
    }
}
