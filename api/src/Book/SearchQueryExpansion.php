<?php

namespace App\Book;

/**
 * Élargit les tokens FTS avec des synonymes du domaine du livre
 * (ex. décès → rendit l'âme / tira sa révérence) sans toucher aux name tokens.
 *
 * Graphies choisies pour bien stemmer en config french (éviter deces→dec).
 */
final class SearchQueryExpansion
{
    /**
     * @var list<list<string>>
     */
    private const GROUPS = [
        [
            'décès', 'mort', 'décédé', 'décédée',
            'âme', 'révérence', 'dépouille', 'rendu',
        ],
        [
            'naissance', 'naquit', 'naquirent',
        ],
        [
            'mariage', 'épousa', 'union', 'marié', 'mariée',
        ],
        [
            'maladie', 'soins', 'santé', 'hôpital',
        ],
    ];

    /**
     * Mots interrogatifs / méta qui bloquent un AND élargi s'ils sont requis
     * (le livre dit « vendredi 19 février », pas « date »).
     *
     * @var array<string, true>
     */
    private const SOFT_TOKENS = [
        'date' => true,
        'dates' => true,
        'jour' => true,
        'année' => true,
        'annee' => true,
        'moment' => true,
        'époque' => true,
        'epoque' => true,
    ];

    /**
     * Phrases rares du corpus à booster quand le groupe décès est actif.
     * Extensible : d'autres groupes pourront déclarer leurs ancres.
     *
     * @var list<string>
     */
    public const DEATH_ANCHORS = [
        "rendit l'âme",
        'tira sa révérence',
        '19 février 1999',
        '19 fevrier 1999',
    ];

    /**
     * Flat expansion (tests / introspection).
     *
     * @param list<string> $tokens
     * @return list<string>
     */
    public static function expand(array $tokens, string $question = ''): array
    {
        $groups = self::andGroups($tokens, $question);
        $out = [];
        foreach ($groups as $group) {
            foreach ($group as $term) {
                $out[$term] = $term;
            }
        }

        return array_values($out);
    }

    /** True when the fallback tsquery uses AND of OR-groups (synonym expansion). */
    public static function isExpandedTsquery(?string $tsquery): bool
    {
        return is_string($tsquery) && str_contains($tsquery, ' & ');
    }

    /**
     * @param list<string> $tokens
     */
    public static function isDeathGroupActive(array $tokens, string $question = ''): bool
    {
        foreach (self::andGroups($tokens, $question) as $group) {
            if (count($group) > 1 && in_array('âme', $group, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build to_tsquery fragment for the OR/expanded fallback channel.
     *
     * When a synonym group fires: AND of (OR-within-group) and hard tokens,
     * e.g. (décès|mort|âme|révérence) & (prophète|kadima).
     * Soft tokens like « date » are dropped from the required AND.
     * When nothing expands: flat OR of original tokens (legacy recall).
     *
     * @param list<string> $tokens
     */
    public static function tsqueryExpanded(array $tokens, string $question = ''): ?string
    {
        if ([] === $tokens) {
            return null;
        }

        $groups = self::andGroups($tokens, $question);
        $expanded = false;
        foreach ($groups as $group) {
            if (count($group) > 1) {
                $expanded = true;
                break;
            }
        }

        if (!$expanded) {
            return implode(' | ', $tokens);
        }

        $parts = [];
        foreach ($groups as $group) {
            $unique = array_values(array_unique($group));
            if ([] === $unique) {
                continue;
            }
            if (1 === count($unique)) {
                $parts[] = $unique[0];
            } else {
                $parts[] = '('.implode(' | ', $unique).')';
            }
        }

        if ([] === $parts) {
            return implode(' | ', $tokens);
        }

        // Multiple hard tokens (prophète, kadima) → OR together so either matches.
        if (count($parts) >= 2) {
            $synParts = [];
            $hardParts = [];
            foreach ($parts as $part) {
                if (str_starts_with($part, '(')) {
                    $synParts[] = $part;
                } else {
                    $hardParts[] = $part;
                }
            }
            $fused = $synParts;
            if (count($hardParts) === 1) {
                $fused[] = $hardParts[0];
            } elseif (count($hardParts) > 1) {
                $fused[] = '('.implode(' | ', $hardParts).')';
            }

            return implode(' & ', $fused);
        }

        return $parts[0];
    }

    /**
     * @param list<string> $tokens
     * @return list<list<string>>
     */
    public static function andGroups(array $tokens, string $question = ''): array
    {
        $index = self::foldedIndex();
        $usedGroupKeys = [];
        $groups = [];
        $hard = [];

        foreach ($tokens as $token) {
            $folded = self::fold($token);
            if (isset(self::SOFT_TOKENS[$folded]) || isset(self::SOFT_TOKENS[$token])) {
                continue;
            }
            if (isset($index[$folded])) {
                $key = self::groupKey($index[$folded]);
                if (isset($usedGroupKeys[$key])) {
                    continue;
                }
                $usedGroupKeys[$key] = true;
                $groups[] = $index[$folded];
                continue;
            }
            $hard[$token] = $token;
        }

        if ($question !== '') {
            $foldedQuestion = self::fold($question);
            foreach (self::GROUPS as $group) {
                $key = self::groupKey($group);
                if (isset($usedGroupKeys[$key])) {
                    continue;
                }
                foreach ($group as $member) {
                    $foldedMember = self::fold($member);
                    if (mb_strlen($foldedMember) < 3) {
                        continue;
                    }
                    if (preg_match('/(?:^|[^\p{L}\p{N}])'.preg_quote($foldedMember, '/').'(?:[^\p{L}\p{N}]|$)/u', $foldedQuestion) !== 1) {
                        continue;
                    }
                    $usedGroupKeys[$key] = true;
                    $groups[] = $group;
                    break;
                }
            }
        }

        foreach ($hard as $token) {
            $groups[] = [$token];
        }

        return $groups;
    }

    /**
     * @param list<string> $group
     */
    private static function groupKey(array $group): string
    {
        $folded = array_map(self::fold(...), $group);
        sort($folded);

        return implode('|', $folded);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function foldedIndex(): array
    {
        static $index = null;
        if (null !== $index) {
            return $index;
        }

        $index = [];
        foreach (self::GROUPS as $group) {
            foreach ($group as $member) {
                $index[self::fold($member)] = $group;
            }
        }

        return $index;
    }

    private static function fold(string $value): string
    {
        return strtr(mb_strtolower($value), [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
            '’' => "'",
        ]);
    }
}
