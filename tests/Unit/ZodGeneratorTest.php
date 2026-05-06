<?php

declare(strict_types=1);

use VincentNdegwa\EloquentTypegen\Support\Generators\ZodGenerator;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ModelMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\EnumMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\RelationMetadata;

it('generates zod schema for a simple model', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('id', 'number', false, true, false);
    $model->fields[] = new FieldMetadata('name', 'string', false, false, false);
    $model->fields[] = new FieldMetadata('email', 'string', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files)->toHaveCount(1);
    expect($files['/tmp/types/user.zod.ts'])->toContain('import { z } from \'zod\'');
    expect($files['/tmp/types/user.zod.ts'])->toContain('export const UserSchema = z.object({');
    expect($files['/tmp/types/user.zod.ts'])->toContain('id: z.number(),');
    expect($files['/tmp/types/user.zod.ts'])->toContain('name: z.string(),');
    expect($files['/tmp/types/user.zod.ts'])->toContain('email: z.string(),');
    expect($files['/tmp/types/user.zod.ts'])->toContain('export type User = z.infer<typeof UserSchema>;');
});

it('handles nullable fields', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('score', 'number', true, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('score: z.number().nullable(),');
});

it('handles optional fields', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('nickname', 'string', false, false, true);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('nickname: z.string().optional(),');
});

it('handles nullable and optional fields', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('bio', 'string', true, false, true);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('bio: z.string().nullable().optional(),');
});

it('generates string enum schemas', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->enums[] = new EnumMetadata('UserRole', "'admin' | 'editor' | 'viewer'");
    $model->fields[] = new FieldMetadata('role', 'UserRole', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('export const UserRoleSchema = z.enum([\'admin\', \'editor\', \'viewer\']);');
    expect($files['/tmp/types/user.zod.ts'])->toContain('role: UserRoleSchema,');
});

it('generates int enum schemas using z.union', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->enums[] = new EnumMetadata('UserStatus', '1 | 0');
    $model->fields[] = new FieldMetadata('status', 'UserStatus', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('export const UserStatusSchema = z.union([z.literal(1), z.literal(0)]);');
    expect($files['/tmp/types/user.zod.ts'])->toContain('status: UserStatusSchema,');
});

it('handles boolean fields', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('active', 'boolean', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('active: z.boolean(),');
});

it('handles Record<string, unknown> type', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('metadata', 'Record<string, unknown>', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('metadata: z.record(z.string(), z.unknown()),');
});

it('handles Date type with z.coerce.date()', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('created_at', 'Date', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('created_at: z.coerce.date(),');
});

it('handles unknown fields', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('custom', 'unknown', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('custom: z.unknown(),');
});

it('includes auto-generated header comment', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('// This file is auto-generated by eloquent-typegen. Do not edit manually.');
});

it('generates CreateSchema and UpdateSchema', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('id', 'number', false, true, false);
    $model->fields[] = new FieldMetadata('name', 'string', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('export const CreateUserSchema = UserSchema.omit({ \'id\', \'created_at\', \'updated_at\', \'deleted_at\' } as const);');
    expect($files['/tmp/types/user.zod.ts'])->toContain('export const UpdateUserSchema = CreateUserSchema.partial();');
});

it('generates relation imports for related models', function () {
    $user = new ModelMetadata('App\\Models\\User', 'User', 'user.ts');
    $post = new ModelMetadata('App\\Models\\Post', 'Post', 'post.ts');

    $user->relations[] = new RelationMetadata('posts', 'Post[]');
    $user->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $generator = new ZodGenerator('/tmp/types', true);
    $files = $generator->generate([$user, $post]);

    expect($files['/tmp/types/user.zod.ts'])->toContain("import { PostSchema } from './post.zod';");
    expect($files['/tmp/types/user.zod.ts'])->toContain('posts: z.array(z.lazy(() => PostSchema)).optional(),');
});

it('uses z.lazy for single relations to handle circular references', function () {
    $user = new ModelMetadata('App\\Models\\User', 'User', 'user.ts');
    $post = new ModelMetadata('App\\Models\\Post', 'Post', 'post.ts');

    $user->relations[] = new RelationMetadata('profile', 'Post');
    $user->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $generator = new ZodGenerator('/tmp/types', true);
    $files = $generator->generate([$user, $post]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('profile: z.lazy(() => PostSchema).optional(),');
});

it('skips self-referential relation imports', function () {
    $quote = new ModelMetadata('App\\Models\\Quote', 'Quote', 'quote.ts');

    $quote->relations[] = new RelationMetadata('parentQuote', 'Quote');
    $quote->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $generator = new ZodGenerator('/tmp/types', true);
    $files = $generator->generate([$quote]);

    expect($files['/tmp/types/quote.zod.ts'])->not->toContain('import { QuoteSchema } from');
    expect($files['/tmp/types/quote.zod.ts'])->toContain('parentQuote: z.lazy(() => QuoteSchema).optional(),');
});

it('handles unknown[] type', function () {
    $model = new ModelMetadata(
        'App\\Models\\User',
        'User',
        'user.ts',
    );

    $model->fields[] = new FieldMetadata('tags', 'unknown[]', false, false, false);

    $generator = new ZodGenerator('/tmp/types', false);
    $files = $generator->generate([$model]);

    expect($files['/tmp/types/user.zod.ts'])->toContain('tags: z.array(z.unknown()),');
});
