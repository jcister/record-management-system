<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function __construct(private readonly ?Request $request = null) {}

    public const SYSTEM_ACTOR_NAME = 'SYSTEM';

    /**
     * Log an action with a friendly description.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        string $description,
        ?Model $auditable = null,
        array $properties = [],
        ?Authenticatable $user = null,
        ?string $actorName = null
    ): AuditLog {
        if ($actorName !== null) {
            $userId = null;
            $userName = $actorName;
        } else {
            $actor = $user instanceof User ? $user : Auth::user();
            $userId = $actor?->getAuthIdentifier();
            $userName = $actor?->name;
        }

        return AuditLog::create([
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'auditable_name' => $this->resolveAuditableName($auditable),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function logModelUpdate(Model $model, string $action, array $changes): AuditLog
    {
        $filtered = collect($changes)
            ->except(['updated_at'])
            ->map(fn ($new, $key) => [
                'from' => $model->getOriginal($key),
                'to' => $new,
            ])
            ->filter(fn (array $change) => $change['from'] !== $change['to'])
            ->all();

        if ($filtered === []) {
            return new AuditLog;
        }

        $description = $this->defaultDescription($action, $model);

        return $this->log($action, $description, $model, ['changes' => $filtered]);
    }

    public function defaultDescription(string $action, ?Model $auditable = null): string
    {
        $name = $this->resolveAuditableName($auditable);
        $actionLabel = ucfirst(str_replace('_', ' ', $action));

        if ($name === null) {
            return $actionLabel;
        }

        return "{$actionLabel}: {$name}";
    }

    private function resolveAuditableName(?Model $auditable): ?string
    {
        if ($auditable === null) {
            return null;
        }

        foreach (['name', 'file_name', 'title', 'request_title'] as $attribute) {
            $value = Arr::get($auditable, $attribute);
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return (string) $auditable->getKey();
    }
}
