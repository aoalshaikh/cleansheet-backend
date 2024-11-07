<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'tenant_id' => $this->tenant_id,
            'avatar_url' => $this->when($this->avatar_path, fn() => 
                Storage::url($this->avatar_path)
            ),
            'preferences' => $this->preferences,
            'roles' => $this->whenLoaded('roles', fn() => 
                $this->roles->pluck('name')
            ),
            'permissions' => $this->whenLoaded('permissions', fn() => 
                $this->getAllPermissions()->pluck('name')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
