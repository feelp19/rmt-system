<?php

namespace App\Http\Resources\User;

use App\Services\XpService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representação do próprio usuário autenticado (inclui e-mail). */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = XpService::progress((int) $this->xp);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? '/api/users/'.$this->id.'/avatar' : null,
            'xp' => $progress['xp'],
            'level' => $progress['level'],
            'level_floor' => $progress['level_floor'],
            'next_level_xp' => $progress['next_level_xp'],
            'created_at' => $this->created_at,
        ];
    }
}
