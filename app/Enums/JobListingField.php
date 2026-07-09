<?php

namespace App\Enums;

enum JobListingField: string
{
    case JobTitle = 'job_title';
    case Url = 'url';
    case Company = 'company';
    case Location = 'location';
    case ApplicationDeadline = 'application_deadline';
    case Salary = 'salary';
    case Description = 'description';
    case EmploymentType = 'employment_type';
    case Department = 'department';
    case PostedAt = 'posted_at';
    case ExternalId = 'external_id';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function requiredValues(): array
    {
        return [
            self::JobTitle->value,
            self::Url->value,
        ];
    }

    public function label(): string
    {
        return config("job_listing_fields.{$this->value}", str_replace('_', ' ', ucfirst($this->value)));
    }
}
