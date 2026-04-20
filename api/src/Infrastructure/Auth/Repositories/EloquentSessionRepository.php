<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Repositories;

use DateTimeImmutable;
use Domain\Auth\Entities\Session;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\ValueObjects\SessionToken;
use Infrastructure\Auth\Models\SessionModel;
use Illuminate\Support\Str;

class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function create(string $userId, SessionToken $token, DateTimeImmutable $expiresAt): Session
    {
        $now = new DateTimeImmutable();
        $id  = (string) Str::uuid();

        SessionModel::create([
            'id'               => $id,
            'user_id'          => $userId,
            'token_hash'       => $token->hash,
            'last_activity_at' => $now->format('Y-m-d H:i:s'),
            'expires_at'       => $expiresAt->format('Y-m-d H:i:s'),
            'created_at'       => $now->format('Y-m-d H:i:s'),
        ]);

        return new Session(
            id:             $id,
            userId:         $userId,
            tokenHash:      $token->hash,
            createdAt:      $now,
            lastActivityAt: $now,
            expiresAt:      $expiresAt,
        );
    }

    public function findByTokenHash(string $hash): ?Session
    {
        $model = SessionModel::where('token_hash', $hash)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function invalidate(string $tokenHash): void
    {
        SessionModel::where('token_hash', $tokenHash)->delete();
    }

    public function invalidateAllForUser(string $userId): void
    {
        SessionModel::where('user_id', $userId)->delete();
    }

    public function purgeExpired(DateTimeImmutable $now): void
    {
        SessionModel::where('expires_at', '<', $now->format('Y-m-d H:i:s'))->delete();
    }

    private function toDomain(SessionModel $model): Session
    {
        return new Session(
            id:             $model->id,
            userId:         $model->user_id,
            tokenHash:      $model->token_hash,
            createdAt:      DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            lastActivityAt: DateTimeImmutable::createFromMutable($model->last_activity_at->toDateTime()),
            expiresAt:      DateTimeImmutable::createFromMutable($model->expires_at->toDateTime()),
        );
    }
}
