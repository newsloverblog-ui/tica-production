<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\RuntimeDocumentStore;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function web(RuntimeDocumentStore $documents): JsonResponse
    {
        return $this->manifest($documents->get(
            RuntimeDocumentStore::WEB_MANIFEST,
            $documents->webManifestDefaults()
        ));
    }

    public function scanner(RuntimeDocumentStore $documents): JsonResponse
    {
        return $this->manifest($documents->get(
            RuntimeDocumentStore::SCANNER_MANIFEST,
            $documents->scannerManifestDefaults()
        ));
    }

    private function manifest(array $content): JsonResponse
    {
        return response()->json($content, 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ], JSON_UNESCAPED_SLASHES);
    }
}
