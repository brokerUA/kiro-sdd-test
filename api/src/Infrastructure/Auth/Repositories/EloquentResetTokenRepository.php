<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Repositories;

use DateTimeImmutable;
use Domain\Auth\Entities\PasswordResetToken;
use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\ValueObjects\ResetToken;
use Infrastructure\Auth\Models\PasswordResetTokenModel;
use Illuminate\Support\Str;

class EloquentResetTokenRepository implements ResetTokenRepositoryInterface
{
    public function create(string $userId, ResetToken $token, DateTimeImmutable $expiresAt): PasswordResetToken
    {
        $now = new DateTimeImmutable();
        $id  = (string) Str::uuid();

        PasswordResetTokenModel::create([
            'id'         => $id,
            'user_id'    => $userId,
            'token_hash' => $token->hash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'used_at'    => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return new PasswordResetToken(
            id:        $id,
            userId:    $userId,
            tokenHash: $token->hash,
            createdAt: $now,
            expiresAt: $expiresAt,
            usedAt:    null,
        );
    }

    public function findByTokenHash(string $hash): ?PasswordResetToken
    {
        $model = PasswordResetTokenModel::where('token_hash', $hash)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function invalidateForUser(string $userId): void
    {
        PasswordResetTokenModel::where('user_id', $userId)->delete();
    }

    public function save(PasswordResetToken $token): void
    {
        PasswordResetTokenModel::where('id', $token->id)->update([
            'used_at' => $token->usedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    private function toDomain(PasswordResetTokenModel $model): PasswordResetToken
    {
        return new PasswordResetToken(
            id:        $model->id,
            userId:    $model->user_id,
            tokenHash: $model->token_hash,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            expiresAt: DateTimeImmutable::createFromMutable($model->expires_at->toDateTime()),
            usedAt:    $model->used_at !== null ? DateTimeImmutable::createFromMutable($model->used_at->toDateTime()) : null,
        );
    }
}
