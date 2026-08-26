<?php

namespace App\Services\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    public function resolve(
        string $eventType,
        array $context
    ): array {
        $recipients = collect();

        if (str_starts_with($eventType, 'application.')) {
            $this->addHolder($recipients, $context);
            $this->addApplicationDepartment($recipients, $context);
        }

        if (str_starts_with($eventType, 'invoice.')) {
            $this->addHolder($recipients, $context);
            $this->addRoles(
                $recipients,
                ['kewangan'],
                'kewangan'
            );
        }

        if (str_starts_with($eventType, 'payment.')) {
            $this->addHolder($recipients, $context);
            $this->addRoles(
                $recipients,
                ['kewangan'],
                'kewangan'
            );
        }

        if (str_starts_with($eventType, 'license.')) {
            $this->addHolder($recipients, $context);
        }

        if (str_starts_with($eventType, 'notice.')) {
            $this->addHolder($recipients, $context);
            $this->addActor(
                $recipients,
                $context,
                'penguatkuasa'
            );
            $this->addApplicationDepartment($recipients, $context);
        }

        if (str_starts_with($eventType, 'legal.')) {
            $this->addHolder($recipients, $context);
            $this->addLegalUsers($recipients);
            $this->addActor(
                $recipients,
                $context,
                'penguatkuasa'
            );
            $this->addApplicationDepartment($recipients, $context);
            $this->addRoles(
                $recipients,
                ['admin', 'ketua_pengarah'],
                'management'
            );
        }

        return $recipients
            ->filter(
                fn (array $item) =>
                    (int) ($item['user_id'] ?? 0) > 0
            )
            ->unique(
                fn (array $item) =>
                    $item['audience'] . ':' . $item['user_id']
            )
            ->values()
            ->all();
    }

    private function addApplicationDepartment(
        Collection $recipients,
        array $context
    ): void {
        $type = strtolower(
            trim(
                (string) (
                    $context['application_type']
                    ?? $context['offence']
                    ?? ''
                )
            )
        );

        if (
            str_contains($type, 'effluent')
            || str_contains($type, 'efluen')
        ) {
            $this->addRoles(
                $recipients,
                ['teknikal_efluen'],
                'department_staff'
            );

            $this->addRoles(
                $recipients,
                ['ketua_unit_efluen'],
                'department_head'
            );

            return;
        }

        $this->addRoles(
            $recipients,
            ['teknikal_badan_perairan'],
            'department_staff'
        );

        $this->addRoles(
            $recipients,
            ['ketua_unit_badan_perairan'],
            'department_head'
        );
    }

    private function addHolder(
        Collection $recipients,
        array $context
    ): void {
        $userId = (int) (
            $context['user_id']
            ?? data_get($context, 'application.user_id', 0)
        );

        if ($userId > 0) {
            $this->addUser(
                $recipients,
                $userId,
                'license_holder'
            );
        }
    }

    private function addActor(
        Collection $recipients,
        array $context,
        string $audience
    ): void {
        $userId = (int) (
            $context['actor_user_id']
            ?? 0
        );

        if ($userId > 0) {
            $this->addUser(
                $recipients,
                $userId,
                $audience
            );
        }
    }

    private function addLegalUsers(
        Collection $recipients
    ): void {
        $modelClass = $this->userModelClass();

        if (!$modelClass) {
            return;
        }

        $users = $modelClass::query()
            ->where(function ($query) {
                $query
                    ->where('user_type', 'like', '%perundangan%')
                    ->orWhere('user_type', 'like', '%undang%')
                    ->orWhere('user_type', 'like', '%legal%');
            })
            ->get();

        foreach ($users as $user) {
            $this->appendUser(
                $recipients,
                $user,
                'perundangan'
            );
        }
    }

    private function addRoles(
        Collection $recipients,
        array $types,
        string $audience
    ): void {
        $modelClass = $this->userModelClass();

        if (!$modelClass) {
            return;
        }

        $users = $modelClass::query()
            ->whereIn('user_type', $types)
            ->get();

        foreach ($users as $user) {
            $this->appendUser(
                $recipients,
                $user,
                $audience
            );
        }
    }

    private function addUser(
        Collection $recipients,
        int $userId,
        string $audience
    ): void {
        $modelClass = $this->userModelClass();

        if (!$modelClass) {
            return;
        }

        $user = $modelClass::query()->find($userId);

        if ($user) {
            $this->appendUser(
                $recipients,
                $user,
                $audience
            );
        }
    }

    private function appendUser(
        Collection $recipients,
        Model $user,
        string $audience
    ): void {
        $recipients->push([
            'user_id' => (int) $user->getKey(),
            'audience' => $audience,
            'user_type' => (string) ($user->user_type ?? ''),
            'name' => (string) (
                $user->name
                ?? $user->full_name
                ?? ''
            ),
            'email' => (string) (
                $user->email
                ?? $user->business_email
                ?? ''
            ),
            'phone' => (string) (
                $user->phone
                ?? $user->phone_no
                ?? $user->mobile_no
                ?? ''
            ),
        ]);
    }

    private function userModelClass(): ?string
    {
        $class = config('auth.providers.users.model');

        if (
            !is_string($class)
            || !class_exists($class)
        ) {
            return null;
        }

        return $class;
    }
}