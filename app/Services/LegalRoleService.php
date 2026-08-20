<?php

namespace App\Services;

class LegalRoleService
{
    private const LEGAL_USER_TYPES = [
        'pegawai_undang_undang',
        'penolong_pegawai_undang_undang',
        'penguatkuasa_perundangan',
        'perundangan',
        'unit_perundangan',
    ];

    public function isLegalUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $userType = $this->normalize(
            $user->user_type ?? ''
        );

        if ($userType === '') {
            return false;
        }

        if (
            in_array(
                $userType,
                self::LEGAL_USER_TYPES,
                true
            )
        ) {
            return true;
        }

        return str_contains(
            $userType,
            'perundangan'
        )
            || str_contains(
                $userType,
                'undang'
            )
            || str_contains(
                $userType,
                'legal'
            );
    }

    public function isLegalDepartment($user): bool
    {
        return $this->isLegalUser($user);
    }

    public function canManageLegal($user): bool
    {
        return $this->isLegalUser($user);
    }

    public function isLegal($user): bool
    {
        return $this->isLegalUser($user);
    }

    public function canManage($user): bool
    {
        return $this->isLegalUser($user);
    }

    public function resolveRole($user): string
    {
        if (!$user) {
            return '';
        }

        return $this->normalize(
            $user->user_type ?? ''
        );
    }

    private function normalize(
        mixed $value
    ): string {
        $text = strtolower(
            trim(
                (string) $value
            )
        );

        $text = str_replace(
            [
                '-',
                ' ',
            ],
            '_',
            $text
        );

        while (
            str_contains(
                $text,
                '__'
            )
        ) {
            $text = str_replace(
                '__',
                '_',
                $text
            );
        }

        return trim(
            $text,
            '_'
        );
    }
}