<?php

declare(strict_types=1);
use App\Models\Project;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

it('has correct fillable attributes', function (): void {
    $project = new Project;
    expect($project->getFillable())->toContain(
        'user_id',
        'name',
        'description',
        'persona_id',
    );
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(Project::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Project::class), true))->toBeTrue();
});
