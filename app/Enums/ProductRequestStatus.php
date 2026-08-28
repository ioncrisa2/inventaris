<?php

namespace App\Enums;

enum ProductRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsInformation = 'needs_information';
    case Considered = 'considered';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Sedang ditinjau',
            self::NeedsInformation => 'Butuh informasi',
            self::Considered => 'Dipertimbangkan',
            self::Planned => 'Direncanakan',
            self::InProgress => 'Sedang dikerjakan',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Duplicate => 'Duplikat',
            self::Closed => 'Ditutup',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'neutral',
            self::UnderReview, self::Considered => 'info',
            self::NeedsInformation => 'warning',
            self::Planned, self::InProgress => 'primary',
            self::Completed => 'success',
            self::Rejected => 'danger',
            self::Duplicate, self::Closed => 'muted',
        };
    }

    public function acceptsTenantReply(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::UnderReview,
            self::NeedsInformation,
            self::Considered,
            self::Planned,
            self::InProgress,
        ], true);
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Duplicate], true);
    }

    /** @return list<self> */
    public function allowedOwnerTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::NeedsInformation, self::Considered, self::Rejected, self::Duplicate, self::Closed],
            self::UnderReview => [self::NeedsInformation, self::Considered, self::Planned, self::Rejected, self::Duplicate, self::Closed],
            self::NeedsInformation => [self::UnderReview, self::Considered, self::Planned, self::Rejected, self::Duplicate, self::Closed],
            self::Considered => [self::NeedsInformation, self::Planned, self::Rejected, self::Duplicate, self::Closed],
            self::Planned => [self::NeedsInformation, self::InProgress, self::Rejected, self::Duplicate, self::Closed],
            self::InProgress => [self::NeedsInformation, self::Completed, self::Rejected, self::Closed],
            self::Completed => [self::InProgress, self::Closed],
            self::Rejected, self::Duplicate => [self::UnderReview, self::Closed],
            self::Closed => [self::Submitted, self::UnderReview],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
