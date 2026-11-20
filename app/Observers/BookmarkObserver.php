<?php

namespace App\Observers;

use App\Models\Bookmark;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class BookmarkObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Bookmark $bookmark): void
    {
        $this->audit->log(
            'bookmark_created',
            $this->actorPrefix().' bookmarked "'.$this->resolveLabel($bookmark).'".',
            $bookmark->bookmarkable
        );
    }

    public function deleted(Bookmark $bookmark): void
    {
        $this->audit->log(
            'bookmark_deleted',
            $this->actorPrefix().' removed bookmark for "'.$this->resolveLabel($bookmark).'".',
            $bookmark->bookmarkable
        );
    }

    private function actorPrefix(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return AuditService::SYSTEM_ACTOR_NAME;
        }

        return $user->name;
    }

    private function resolveLabel(Bookmark $bookmark): string
    {
        $target = $bookmark->bookmarkable;

        if ($target === null) {
            return 'item';
        }

        foreach (['name', 'file_name', 'title'] as $attribute) {
            $value = $target->{$attribute} ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return (string) $target->getKey();
    }
}
