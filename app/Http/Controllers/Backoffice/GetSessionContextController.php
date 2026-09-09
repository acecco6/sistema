<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\GetSessionContextHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetSessionContextController extends Controller
{
    public function __invoke(Request $request, GetSessionContextHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->handle((int) $request->user()->id), 'Contexto de sesión obtenido correctamente.');
    }
}
