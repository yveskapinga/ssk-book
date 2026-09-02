<?php

namespace App\Book;

use App\Shared\DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class BookFileStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/books')] private string $storageDirectory)
    {
    }

    public function store(string $versionId, UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new DomainException('Le fichier transmis doit être un PDF valide.');
        }

        $mime = $file->getClientMimeType();
        try {
            $detected = $file->getMimeType();
            if (is_string($detected) && '' !== $detected) {
                $mime = $detected;
            }
        } catch (\LogicException) {
        }
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ('application/pdf' !== $mime && 'pdf' !== $extension) {
            throw new DomainException('Le fichier transmis doit être un PDF valide.');
        }
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw new DomainException('Le fichier PDF ne peut pas dépasser 50 Mo.');
        }

        $directory = $this->storageDirectory.'/'.$versionId;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire du livre.');
        }
        $path = $directory.'/source.pdf';
        $file->move($directory, 'source.pdf');

        return ['path' => $path, 'sha256' => hash_file('sha256', $path)];
    }

    public function remove(string $versionId): void
    {
        $path = $this->storageDirectory.'/'.$versionId.'/source.pdf';
        if (is_file($path)) {
            unlink($path);
        }
    }
}
