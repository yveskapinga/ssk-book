<?php

namespace App\Book;

/**
 * Relie les sourceIds du modèle aux passages réellement récupérés.
 *
 * Pourquoi : Llama 3.2 cite souvent un numéro de page au lieu de l’UUID.
 * Garantit : pas de 500 si la réponse est ancrée dans les sources envoyées.
 * Ne fait pas : relancer l’inférence ; inventer un passage.
 */
final class CitedSourceResolver
{
    /**
     * @param list<mixed> $citedIds
     * @param list<array<string, mixed>> $retrieved
     * @return list<string>
     */
    public static function resolve(array $citedIds, array $retrieved, bool $insufficientEvidence): array
    {
        $allowed = array_column($retrieved, null, 'id');
        $ids = [];
        foreach ($citedIds as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            if (isset($allowed[$raw])) {
                $ids[] = $raw;
                continue;
            }
            if (preg_match_all('/\d+/', $raw, $matches) === 0) {
                continue;
            }
            foreach ($matches[0] as $num) {
                $page = (int) $num;
                foreach ($retrieved as $source) {
                    $start = (int) ($source['start_page'] ?? 0);
                    $end = (int) ($source['end_page'] ?? $start);
                    if ($page >= $start && $page <= $end) {
                        $ids[] = (string) $source['id'];
                    }
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === [] && !$insufficientEvidence && $retrieved !== []) {
            return array_values(array_map(static fn (array $source): string => (string) $source['id'], $retrieved));
        }

        return $ids;
    }
}
