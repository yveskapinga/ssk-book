<?php

namespace App\Book;

final class SearchQuestionTerms
{
    /** @var array<string, true> */
    private const STOPWORDS = [
        'alors' => true, 'après' => true, 'aussi' => true, 'avant' => true, 'avec' => true,
        'avait' => true, 'avoir' => true, 'bien' => true, 'cette' => true, 'comme' => true,
        'dans' => true, 'dont' => true, 'elle' => true, 'elles' => true, 'encore' => true,
        'entre' => true, 'estce' => true, 'leur' => true, 'leurs' => true, 'livre' => true,
        'mentionne' => true, 'nous' => true, 'plus' => true, 'pour' => true,
        'quand' => true, 'quel' => true, 'quelle' => true, 'quels' => true, 'quelles' => true,
        'comment' => true, 'pourquoi' => true, 'combien' => true, 'lequel' => true,
        'laquelle' => true, 'lesquels' => true, 'lesquelles' => true, 'lieu' => true,
        'tout' => true, 'tous' => true, 'très' => true, 'vous' => true,
    ];

    /** Filtrés sauf si la question porte explicitement sur le Prophète KADIMA. */
    private const PROPHET_STOPWORDS = [
        'kadima' => true,
        'prophete' => true,
        'prophète' => true,
    ];

    /**
     * Tokens originaux (name matching + AND FTS). Pas d'expansion synonymes.
     *
     * @return list<string>
     */
    public static function tokens(string $question): array
    {
        $subject = QuestionSubject::detect($question);
        if (preg_match_all('/[\p{L}\p{N}]{4,}/u', $question, $matches) < 1) {
            return [];
        }

        $tokens = [];
        foreach ($matches[0] as $word) {
            $normalized = mb_strtolower($word);
            $folded = strtr($normalized, [
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
                'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
            ]);
            if (isset(self::STOPWORDS[$normalized]) || isset(self::STOPWORDS[$folded])) {
                continue;
            }
            if ($subject !== QuestionSubject::PROPHET
                && (isset(self::PROPHET_STOPWORDS[$normalized]) || isset(self::PROPHET_STOPWORDS[$folded]))
            ) {
                continue;
            }
            $tokens[$normalized] = $normalized;
        }

        return array_values($tokens);
    }

    /**
     * Requête plein texte : uniquement les mots porteurs originaux, pour éviter
     * qu’un « quand a eu lieu » empêche de trouver le passage daté.
     */
    public static function tsquery(string $question): ?string
    {
        $tokens = self::tokens($question);
        if ([] === $tokens) {
            return null;
        }

        return implode(' & ', $tokens);
    }

    /**
     * Fallback FTS : AND de groupes synonymes (OR internes), sans exiger
     * les tokens mous (« date »). Sans expansion → OR plat des tokens.
     */
    public static function tsqueryOr(string $question): ?string
    {
        return SearchQueryExpansion::tsqueryExpanded(self::tokens($question), $question);
    }
}
