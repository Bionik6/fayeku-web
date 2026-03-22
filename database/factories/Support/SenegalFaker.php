<?php

namespace Database\Factories\Support;

/**
 * Générateur de données fictives à consonance sénégalaise.
 */
class SenegalFaker
{
    /** @var array<string> */
    private static array $lastNames = [
        'Diallo', 'Ndiaye', 'Fall', 'Sow', 'Mbaye', 'Bâ', 'Gueye', 'Diop',
        'Kane', 'Sy', 'Touré', 'Traoré', 'Konaté', 'Coulibaly', 'Sarr',
        'Diouf', 'Faye', 'Cissé', 'Ly', 'Ndour', 'Thiaw', 'Lô', 'Dème',
        'Coly', 'Badji', 'Kouyaté', 'Sène', 'Djiba', 'Sambou', 'Manga',
    ];

    /** @var array<string> */
    private static array $firstNamesMale = [
        'Ousmane', 'Mamadou', 'Ibrahima', 'Abdoulaye', 'Cheikh', 'Moussa',
        'Modou', 'Idrissa', 'Seydou', 'Alioune', 'Lamine', 'Babacar',
        'Pape', 'Boubacar', 'Aliou', 'Serigne', 'Mor', 'Demba', 'Samba',
        'Bamba', 'Assane', 'Daouda', 'Malick', 'Omar', 'Habib',
    ];

    /** @var array<string> */
    private static array $firstNamesFemale = [
        'Mariama', 'Fatou', 'Aissatou', 'Rokhaya', 'Aminata', 'Adja',
        'Binta', 'Coumba', 'Ndéye', 'Khady', 'Sokhna', 'Rama',
        'Mame', 'Astou', 'Dieynaba', 'Yaye', 'Penda', 'Seynabou',
        'Fatoumata', 'Awa', 'Ndeye', 'Marème', 'Khoudia', 'Oumou',
    ];

    /** @var array<string> */
    private static array $companySuffixes = [
        'SARL', 'SA', 'SAS', 'EURL', 'GIE',
    ];

    /** @var array<string> */
    private static array $companyActivities = [
        'Commerce', 'Services', 'Industries', 'Immobilier', 'BTP',
        'Transport', 'Distribution', 'Tech', 'Digital', 'Consulting',
        'Pharma', 'Import-Export', 'Agro', 'Investissements', 'Solutions',
    ];

    /** @var array<string> */
    private static array $neighborhoods = [
        'Plateau', 'Médina', 'Parcelles Assainies', 'Grand Dakar', 'HLM',
        'Fann Résidence', 'Point E', 'Almadies', 'Ngor', 'Ouakam',
        'Yoff', 'Pikine', 'Guédiawaye', 'Liberté 6', 'Mermoz',
        'Sacré-Cœur', 'Sicap Baobab', 'Dieuppeul', 'Gueule Tapée',
    ];

    /** @var array<string> */
    private static array $cities = [
        'Dakar', 'Thiès', 'Saint-Louis', 'Ziguinchor', 'Kaolack',
        'Touba', 'Mbour', 'Rufisque', 'Diourbel', 'Louga',
    ];

    /** @var array<string> */
    private static array $emailDomains = [
        'gmail.com', 'orange.sn', 'free.sn', 'yahoo.fr',
        'outlook.com', 'hotmail.fr',
    ];

    public static function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public static function firstName(): string
    {
        $all = array_merge(self::$firstNamesMale, self::$firstNamesFemale);

        return $all[array_rand($all)];
    }

    public static function firstNameMale(): string
    {
        return self::$firstNamesMale[array_rand(self::$firstNamesMale)];
    }

    public static function firstNameFemale(): string
    {
        return self::$firstNamesFemale[array_rand(self::$firstNamesFemale)];
    }

    /** Ex: "Diallo & Associés SARL" ou "Mbaye Distribution SA" */
    public static function companyName(): string
    {
        $lastName = self::lastName();
        $activity = self::$companyActivities[array_rand(self::$companyActivities)];
        $suffix = self::$companySuffixes[array_rand(self::$companySuffixes)];

        $patterns = [
            "{$lastName} {$activity} {$suffix}",
            "{$lastName} & Associés {$suffix}",
            "{$lastName} & Frères {$suffix}",
            "Groupe {$lastName} {$suffix}",
            "{$lastName} {$activity}",
        ];

        return $patterns[array_rand($patterns)];
    }

    /** Ex: "Plateau, Dakar, Sénégal" */
    public static function address(): string
    {
        $num = rand(1, 250);
        $neighborhood = self::$neighborhoods[array_rand(self::$neighborhoods)];
        $city = self::$cities[array_rand(self::$cities)];

        return "Villa {$num}, {$neighborhood}, {$city}, Sénégal";
    }

    /** Génère un email à partir d'un nom */
    public static function email(string $firstName = '', string $lastName = ''): string
    {
        $first = $firstName ?: self::firstName();
        $last = $lastName ?: self::lastName();
        $domain = self::$emailDomains[array_rand(self::$emailDomains)];

        $local = strtolower(
            iconv('UTF-8', 'ASCII//TRANSLIT', $first).'.'.iconv('UTF-8', 'ASCII//TRANSLIT', $last)
        );
        $local = preg_replace('/[^a-z0-9._-]/', '', $local ?? '');

        return $local.'@'.$domain;
    }

    /** Numéro sénégalais : +221 7X XXX XX XX */
    public static function phone(): string
    {
        $prefix = ['70', '75', '76', '77', '78'];
        $p = $prefix[array_rand($prefix)];

        return '+221'.$p.rand(100, 999).rand(10, 99).rand(10, 99);
    }
}
