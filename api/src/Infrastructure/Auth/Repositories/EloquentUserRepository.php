<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Repositories;

use DateTimeImmutable;
use Domain\Auth\Entities\User;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\HashedPassword;
use Infrastructure\Auth\Models\UserModel;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        $model = UserModel::find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::where('email', $email->value)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(User $user): void
    {
        $exists = UserModel::find($user->id) !== null;

        $data = [
            'id'            => $user->id,
            'email'         => $user->email->value,
            'password_hash' => $user->passwordHash->hash,
            'created_at'    => $user->createdAt->format('Y-m-d H:i:s'),
            'updated_at'    => $user->updatedAt->format('Y-m-d H:i:s'),
        ];

        if ($exists) {
            UserModel::where('id', $user->id)->update([
                'email'         => $data['email'],
                'password_hash' => $data['password_hash'],
                'updated_at'    => $data['updated_at'],
            ]);
        } else {
            UserModel::create($data);
        }
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id:           $model->id,
            email:        new Email($model->email),
            passwordHash: new HashedPassword($model->password_hash),
            createdAt:    DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            updatedAt:    DateTimeImmutable::createFromMutable($model->updated_at->toDateTime()),
        );
    }
}
