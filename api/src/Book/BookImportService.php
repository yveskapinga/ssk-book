<?php

namespace App\Book;

use App\Audit\AuditRepository;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

final readonly class BookImportService
{
    public function __construct(
        private Connection $connection,
        private BookRepository $books,
        private IngestionJobRepository $jobs,
        private BookFileStorage $storage,
        private AuditRepository $audit,
    ) {
    }

    public function upload(User $actor, UploadedFile $file, string $title, string $label, ?string $description): array
    {
        $title = trim($title);
        $label = trim($label);
        if (mb_strlen($title) < 2 || mb_strlen($title) > 255) {
            throw new DomainException('Le titre du livre est invalide.');
        }
        if (mb_strlen($label) < 1 || mb_strlen($label) > 120) {
            throw new DomainException('Le libellé de version est invalide.');
        }

        $slug = strtolower((new AsciiSlugger())->slug($title)->toString());
        $versionId = Uuid::v7()->toRfc4122();
        $jobId = Uuid::v7()->toRfc4122();
        $stored = $this->storage->store($versionId, $file);

        try {
            return $this->connection->transactional(function () use ($actor, $title, $label, $description, $slug, $versionId, $jobId, $stored, $file): array {
                $book = $this->books->findBySlug($slug);
                if (null === $book) {
                    $bookId = Uuid::v7()->toRfc4122();
                    $this->books->create($bookId, $slug, $title, $description ? trim($description) : null);
                } else {
                    $bookId = $book['id'];
                }
                $versionNumber = $this->books->lockAndNextVersionNumber($bookId);
                $this->books->createVersion([
                    'id' => $versionId,
                    'book_id' => $bookId,
                    'version_number' => $versionNumber,
                    'label' => $label,
                    'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'source_path' => $stored['path'],
                    'source_sha256' => $stored['sha256'],
                    'created_by' => $actor->id,
                ]);
                $this->jobs->create($jobId, $versionId);
                $this->audit->append($actor->id, 'BOOK_VERSION_UPLOADED', 'BOOK_VERSION', $versionId, ['jobId' => $jobId]);

                return ['bookId' => $bookId, 'versionId' => $versionId, 'versionNumber' => $versionNumber, 'jobId' => $jobId, 'status' => 'PENDING'];
            });
        } catch (\Throwable $exception) {
            $this->storage->remove($versionId);
            throw $exception;
        }
    }
}
