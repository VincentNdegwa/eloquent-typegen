---
name: eloquent-typegen
description: "Use this skill for Laravel Eloquent TypeGen which generates TypeScript types from Laravel models. ALWAYS use this skill when working with TypeScript types that should match Laravel models. Trigger when: generating TypeScript types for Laravel models, updating model casts or relationships, adding migrations with nullable columns, using PHP enums in models, configuring type generation, or running typegen:generate command. Covers: typegen:generate command, model $casts, PHP enums, migration reading for nullability, relationships, config/typegen.php options. Do not use for backend-only tasks, database queries, or non-TypeScript frontend work."
license: MIT
metadata:
  author: VincentNdegwa
---

# Laravel Eloquent TypeGen

Eloquent TypeGen generates TypeScript types from Laravel models by reading `$casts`, PHP enums, migrations, and relationships.

## Quick Reference

### Generate Types

Generate TypeScript types for all models:
```bash
php artisan typegen:generate
```

Generate for specific models only:
```bash
php artisan typegen:generate --model=User --model=Post
```

Preview without writing files:
```bash
php artisan typegen:generate --dry-run
```

Custom output path:
```bash
php artisan typegen:generate --path=src/types/api
```

Skip relationships:
```bash
php artisan typegen:generate --no-relations
```

### Publish Config

```bash
php artisan vendor:publish --tag=typegen-config
```

Config file: `config/typegen.php`

### Configuration

Key options in `config/typegen.php`:

- `model_paths`: Directories to scan for models (default: `['Models']`)
- `output_path`: Where to write `.ts` files (default: `resources/js/types/models`)
- `generate_index`: Generate index.ts barrel file (default: `true`)
- `generate_helpers`: Generate model-helpers.ts with Nullable<T>, Paginated<T> (default: `true`)
- `date_type`: How dates are typed - `string` or `Date` (default: `string`)
- `read_migrations`: Parse migrations for nullable columns (default: `true`)
- `infer_types_from_migrations`: Use migration column types as fallback when casts are missing (default: `true`)
- `migration_type_map`: Override or add to the default migration type mappings
- `include_relationships`: Include relationship methods (default: `true`)
- `custom_type_map`: Override TypeScript types for specific cast classes

## Type Mapping

| PHP / Laravel cast | TypeScript type |
|---|---|
| `int`, `integer`, `bigInteger` | `number` |
| `float`, `double`, `decimal` | `number` |
| `bool`, `boolean` | `boolean` |
| `string`, `char`, `text`, `uuid` | `string` |
| `date`, `datetime`, `timestamp` | `string` (or `Date` if configured) |
| `array`, `json`, `object` | `Record<string, unknown>` |
| `collection` | `unknown[]` |
| `BackedEnum` (string) | Union of string literals |
| `BackedEnum` (int) | Union of number literals |
| `UnitEnum` | Union of case name strings |
| `AsCollection`, `AsArrayObject` | `unknown[]` |
| `AsStringable` | `string` |
| `AsEnumCollection:MyEnum` | `MyEnum[]` |

## Custom Casts

Custom casts default to `unknown`. Override via:

**Config map:**
```php
'custom_type_map' => [
  'App\Casts\Money' => '{ amount: number; currency: string }',
],
```

**Cast class method:**
```php
class MoneyCast
{
    public static function toTypeScript(): string
    {
        return '{ amount: number; currency: string }';
    }
}
```

## Usage Patterns

### Import Generated Types

```typescript
import type { User, CreateUserPayload, Paginated } from '@/types/models'
```

### Vue 3

```vue
<script setup lang="ts">
import type { User } from '@/types/models'
const props = defineProps<{ user: User }>()
</script>
```

### React

```tsx
import type { User } from '@/types/models'
function UserCard({ user }: { user: User }) {
  return <div>{user.name}</div>
}
```

## Common Pitfalls

- Forgetting to run `typegen:generate` after model changes
- Not regenerating types after migration changes (nullable columns)
- Using `$guarded = []` without migrations enabled (falls back to columns only)
- Not adding hidden fields to `$hidden` (they appear in generated types)
- Custom casts without `toTypeScript()` method or config map default to `unknown`

## Package Info

- Package: `composer require vincentndegwa/eloquent-typegen --dev`
- Namespace: `VincentNdegwa\EloquentTypegen`
- Command: `php artisan typegen:generate`
- Config: `config/typegen.php`
