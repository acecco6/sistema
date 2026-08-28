<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\AuditLog;
use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
        $middleware->api(append: [
            AuditLog::class,
        ]);;


        $middleware->redirectTo(
            guests: function (Request $request) {
                if ($request->is('api/*')) {
                    return null;
                }
                return route('login');
            }
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Configura la respuesta cuando el usuario no está autenticado
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status' => false,
                'message' => 'No estás autenticado. Por favor, inicia sesión.',
            ], 401);
        });

        $responder = new class {
            use \App\Shared\Http\Responses\ApiResponse;

            public function triggerError(string $message, int $code, mixed $errors = null)
            {
                return $this->errorResponse($message, $code, $errors);
            }
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($responder) {
            return $responder->triggerError(
                'Los datos proporcionados no son válidos.',
                422,
                $e->errors()
            );
        });


        $exceptions->render(function (
            NotFoundHttpException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recurso no encontrado.',
                    'data' => null,
                    'code' => 404,
                ], 404);
            }

            return null;
        });
        $exceptions->render(function (DomainException $e, Request $request) use ($responder) {
            return $responder->triggerError(
                $e->getMessage(),
                $e->getCode()
            );
        });

        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
