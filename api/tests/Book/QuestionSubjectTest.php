<?php

namespace App\Tests\Book;

use App\Book\QuestionSubject;
use PHPUnit\Framework\TestCase;

final class QuestionSubjectTest extends TestCase
{
    public function testDetectsAuthorFamilyQuestion(): void
    {
        self::assertSame(
            QuestionSubject::AUTHOR,
            QuestionSubject::detect("L'auteur du livre avait combien d'enfants ?")
        );
    }

    public function testDetectsProphetFamilyQuestion(): void
    {
        self::assertSame(
            QuestionSubject::PROPHET,
            QuestionSubject::detect('Combien d\'enfants avait le prophète KADIMA ?')
        );
        self::assertSame(
            QuestionSubject::PROPHET,
            QuestionSubject::detect('les enfants du prophète KADIMA')
        );
    }

    public function testAuthorWinsWhenBothMentioned(): void
    {
        self::assertSame(
            QuestionSubject::AUTHOR,
            QuestionSubject::detect("Que dit l'auteur à propos du prophète KADIMA ?")
        );
    }

    public function testFamilyIntentTrueForChildrenQuestions(): void
    {
        self::assertTrue(QuestionSubject::isFamilyIntent('Combien d\'enfants avait le prophète KADIMA ?'));
        self::assertTrue(QuestionSubject::isFamilyIntent('arbre généalogique de la famille'));
    }

    public function testFamilyIntentFalseForDeathDateQuestion(): void
    {
        self::assertFalse(QuestionSubject::isFamilyIntent('Quelle est la date du décès du prophète Kadima ?'));
        self::assertFalse(QuestionSubject::isFamilyIntent('Le prophète kadima a rendu l\'âme à quelle date?'));
    }
}
