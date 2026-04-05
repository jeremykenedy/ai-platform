<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Persona\StorePersonaRequest;
use App\Http\Requests\Persona\UpdatePersonaRequest;
use App\Http\Resources\PersonaCollection;
use App\Http\Resources\PersonaResource;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonaController extends Controller
{
    public function index(Request $request): PersonaCollection
    {
        /** @var User $user */
        $user = $request->user();

        $personas = $user->personas()->cursorPaginate(20);

        return new PersonaCollection($personas);
    }

    public function store(StorePersonaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $persona = $user->personas()->create($request->validated());

        return (new PersonaResource($persona))->response()->setStatusCode(201);
    }

    public function show(Persona $persona): PersonaResource
    {
        $this->authorize('view', $persona);

        return new PersonaResource($persona);
    }

    public function update(UpdatePersonaRequest $request, Persona $persona): PersonaResource
    {
        $this->authorize('update', $persona);

        $persona->update($request->validated());

        return new PersonaResource($persona);
    }

    public function destroy(Persona $persona): JsonResponse
    {
        $this->authorize('delete', $persona);

        $persona->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
