<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform RBAC roles. One primary role per user account (users.role).
 * Authorization is enforced server-side via policies + middleware only.
 */
enum UserRole: string
{
    case JobSeeker = 'job_seeker';
    case EmployerAdmin = 'employer_admin';
    case EmployerRecruiter = 'employer_recruiter';
    case UniversityAdmin = 'university_admin';
    case UniversityStaff = 'university_staff';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    public function isEmployer(): bool
    {
        return $this === self::EmployerAdmin || $this === self::EmployerRecruiter;
    }

    public function isUniversity(): bool
    {
        return $this === self::UniversityAdmin || $this === self::UniversityStaff;
    }

    public function isAdminTier(): bool
    {
        return in_array($this, [self::Moderator, self::Admin, self::SuperAdmin], true);
    }
}
