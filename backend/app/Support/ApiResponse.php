<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Satu-satunya tempat bentuk response API dirakit.
 *
 * Format: { "success": bool, "data"|"message": ... }
 * Lihat docs/03-api-spec.md §1.1. Jangan merakit JSON response di controller
 * secara manual — kalau bentuknya berbeda-beda, frontend harus menebak.
 */
class ApiResponse
{
    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    public static function created(mixed $data = null): JsonResponse
    {
        return self::ok($data, 201);
    }

    /** @param  array<string, array<int, string>>|null  $errors */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Paginasi dengan meta yang konsisten. Frontend memakai `meta.last_page`
     * untuk kontrol halaman, jadi bentuknya tidak boleh berubah-ubah.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function paginated(LengthAwarePaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = $paginator->getCollection();

        if ($transform !== null) {
            $items = $items->map($transform);
        }

        return response()->json([
            'success' => true,
            'data' => $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
