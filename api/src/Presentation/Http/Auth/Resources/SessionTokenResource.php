<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SessionTokenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'session_token' => $this->raw,
        ];
    }
}
