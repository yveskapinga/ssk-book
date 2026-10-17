<?php

namespace App\Book;

/**
 * Distingue le sujet d'une question Ask-the-book quand auteur et Prophète
 * KADIMA peuvent être confondus (même patronyme, récit à la 1re personne).
 */
final class QuestionSubject
{
    public const AUTHOR = 'author';
    public const PROPHET = 'prophet';
    public const OTHER = 'other';

    public static function detect(string $question): string
    {
        $normalized = mb_strtolower($question);
        $folded = strtr($normalized, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
            '’' => "'",
        ]);

        $mentionsAuthor = (bool) preg_match(
            '/\b(auteur|ecrivain|narrateur|redacteur)\b/u',
            $folded
        ) || str_contains($folded, 'qui a ecrit');

        $mentionsProphet = (bool) preg_match(
            '/\b(prophete|souverain\s+sacrificateur)\b/u',
            $folded
        ) || (bool) preg_match('/\bkadima\b/u', $folded);

        if ($mentionsAuthor && !$mentionsProphet) {
            return self::AUTHOR;
        }
        if ($mentionsProphet && !$mentionsAuthor) {
            return self::PROPHET;
        }
        if ($mentionsAuthor && $mentionsProphet) {
            // Priorité à « auteur » si les deux apparaissent (« l'auteur sur Kadima »).
            return self::AUTHOR;
        }

        return self::OTHER;
    }
}
