<?php

namespace App\Http\Resources\User;

use App\Services\XpService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Entrada do leaderboard público (ranking por XP). */
class RankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_path ? '/api/users/'.$this->id.'/avatar' : null,
            'xp' => (int) $this->xp,
            'level' => XpService::levelForXp((int) $this->xp),
        ];
    }
}
