<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthFlowTest extends WebTestCase
{
    public function testRegisterLoginAndProfile(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');
        $health = json_decode($client->getResponse()->getContent() ?: '{}', true);
        if (($health['database'] ?? '') !== 'up') {
            self::markTestSkipped('PostgreSQL is not available.');
        }

        $email = sprintf('reader-%s@example.test', bin2hex(random_bytes(4)));
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'displayName' => 'Lecteur Test',
            'password' => 'CorrectHorseBattery-9',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $registered = json_decode($client->getResponse()->getContent() ?: '{}', true);
        self::assertArrayHasKey('token', $registered['data'] ?? []);

        $client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$registered['data']['token'],
        ]);
        self::assertResponseIsSuccessful();
        $profile = json_decode($client->getResponse()->getContent() ?: '{}', true);
        self::assertSame($email, $profile['data']['user']['email'] ?? null);

        $client->request('GET', '/api/library', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$registered['data']['token'],
        ]);
        self::assertResponseIsSuccessful();
        $library = json_decode($client->getResponse()->getContent() ?: '{}', true);
        self::assertArrayHasKey('items', $library['data'] ?? []);
    }
}
