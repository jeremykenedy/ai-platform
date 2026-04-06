<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Training\CancelTrainingAction;
use App\Actions\Training\StartTrainingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StartTrainingRequest;
use App\Http\Requests\Training\StoreDatasetRequest;
use App\Http\Resources\TrainingDatasetResource;
use App\Http\Resources\TrainingJobResource;
use App\Models\TrainingDataset;
use App\Models\TrainingJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TrainingController extends Controller
{
    public function __construct(
        private readonly StartTrainingAction $startTrainingAction,
        private readonly CancelTrainingAction $cancelTrainingAction,
    ) {}

    public function datasetsIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $datasets = $user->trainingDatasets()->cursorPaginate(20);

        return response()->json(TrainingDatasetResource::collection($datasets));
    }

    public function datasetsStore(StoreDatasetRequest $request): JsonResponse
    {
        $this->authorize('create', TrainingDataset::class);

        /** @var User $user */
        $user = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $path = $file->store("training-datasets/{$user->id}", 'local');

        $dataset = $user->trainingDatasets()->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'format' => $request->validated('format'),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
        ]);

        return (new TrainingDatasetResource($dataset))->response()->setStatusCode(201);
    }

    public function datasetsDestroy(TrainingDataset $dataset): JsonResponse
    {
        $this->authorize('delete', $dataset);

        if ($dataset->path !== null) {
            Storage::disk('local')->delete($dataset->path);
        }

        $dataset->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function jobsIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $jobs = $user->trainingJobs()
            ->with(['dataset', 'baseModel'])
            ->cursorPaginate(20);

        return response()->json(TrainingJobResource::collection($jobs));
    }

    public function jobsStore(StartTrainingRequest $request): JsonResponse
    {
        $this->authorize('create', TrainingJob::class);

        /** @var User $user */
        $user = $request->user();

        $job = TrainingJob::create(array_merge($request->validated(), ['user_id' => (string) $user->id]));

        $this->startTrainingAction->handle($job);

        return (new TrainingJobResource($job->fresh()))->response()->setStatusCode(201);
    }

    public function jobsShow(TrainingJob $job): TrainingJobResource
    {
        $this->authorize('view', $job);

        $job->loadMissing(['dataset', 'baseModel']);

        return new TrainingJobResource($job);
    }

    public function jobsCancel(TrainingJob $job): TrainingJobResource
    {
        $this->authorize('cancel', $job);

        $this->cancelTrainingAction->handle($job);

        return new TrainingJobResource($job->fresh());
    }
}
