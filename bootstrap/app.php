<?php

use App\Exceptions\InvalidMessagePasswordException;
use App\Exceptions\MessageAlreadyViewedException;
use App\Exceptions\MessageDestroyedException;
use App\Exceptions\MessageSealedException;
use App\Http\Resources\TombstoneResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (MessageSealedException $e) => response()->json([
            'message' => $e->getMessage(),
            'unlock_at' => $e->vaultMessage->unlock_at,
            'seconds_remaining' => $e->vaultMessage->secondsUntilUnlock(),
        ], 423));

        $exceptions->render(fn (MessageDestroyedException $e) => response()->json([
            'message' => $e->getMessage(),
            'tombstone' => new TombstoneResource($e->vaultMessage),
        ], 410));

        $exceptions->render(fn (InvalidMessagePasswordException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 403));

        $exceptions->render(fn (MessageAlreadyViewedException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));

        $exceptions->render(fn (InvalidSignatureException $e) => response()->json([
            'message' => 'The download link is invalid or has expired.',
        ], 403));

        $exceptions->render(fn (PostTooLargeException $e) => response()->json([
            'message' => 'The request is larger than the server upload limit.',
        ], 413));

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Resource not found.'], 404)
            : null);
    })->create();
