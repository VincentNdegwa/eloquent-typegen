<?php

declare(strict_types=1);

use Based\EloquentTypegen\Support\Generators\TypeScriptGenerator;
use Based\EloquentTypegen\Support\Metadata\AccessorMetadata;
use Based\EloquentTypegen\Support\Metadata\EnumMetadata;
use Based\EloquentTypegen\Support\Metadata\FieldMetadata;
use Based\EloquentTypegen\Support\Metadata\ModelMetadata;
use Based\EloquentTypegen\Support\Metadata\RelationMetadata;

it('renders a model file with enums and relations', function () {
    $model = new ModelMetadata('App\\Models\\Note', 'Note', 'note.ts');
    $model->fields[] = new FieldMetadata('id', 'number');
    $model->fields[] = new FieldMetadata('title', 'string', true, false, true);
    $model->accessors[] = new AccessorMetadata('summary');
    $model->relations[] = new RelationMetadata('tags', 'Tag[]');
    $model->enums[] = new EnumMetadata('Status', "'draft' | 'published'");

    $generator = new TypeScriptGenerator('tests-output', true);
    $files = $generator->generate([$model]);

    $content = $files[base_path('tests-output/note.ts')];

    expect($content)
        ->toContain('export type Status =')
        ->toContain('export interface Note')
        ->toContain('title?: Nullable<string>')
        ->toContain('readonly summary?: unknown')
        ->toContain('tags?: Tag[]');
});
