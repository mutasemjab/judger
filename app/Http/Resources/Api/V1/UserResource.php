<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'user_type' => $this->user_type?->value,
            'account_status' => $this->account_status?->value,
            'email_verified_at' => $this->email_verified_at,
            'language' => $this->language,
            'theme' => $this->theme,
            'biometric_enabled' => $this->biometric_enabled,
            'notification_preferences' => $this->notification_preferences,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
