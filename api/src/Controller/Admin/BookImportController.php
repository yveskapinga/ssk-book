<?php

namespace App\Controller\Admin;

use App\Book\BookImportService;
use App\Book\IngestionService;
use App\Controller\ApiController;
use App\Identity\User;
use App\Shared\DomainException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/books', name: 'api_admin_books_')]
final class BookImportController extends ApiController
{
    public function __construct(private readonly BookImportService $imports, private readonly IngestionService $ingestion)
    {
    }

    #[Route('/imports', name: 'import', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $actor = $this->authenticatedUser();
            $file = $request->files->get('file');
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                throw new DomainException('Le fichier PDF est obligatoire.');
            }
            $result = $this->imports->upload(
                $actor,
                $file,
                (string) $request->request->get('title', ''),
                (string) $request->request->get('label', ''),
                $request->request->get('description'),
            );

            return $this->success($result, 202);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/ingestion-jobs/{id}', name: 'ingestion_status', methods: ['GET'])]
    public function status(string $id): JsonResponse
    {
        try {
            return $this->success($this->ingestion->status($id));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/ingestion-jobs/{id}/run', name: 'ingestion_run', methods: ['POST'])]
    public function run(string $id): JsonResponse
    {
        try {
            return $this->success($this->ingestion->run($this->authenticatedUser(), $id));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Authenticated user is unavailable.');
        }

        return $user;
    }
}
