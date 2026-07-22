<?php

namespace App\Enums;

enum IdentityVerificationStatus: string
{
    case NotVerified = 'not_verified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case NotFound = 'not_found';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case ProviderUnavailable = 'provider_unavailable';
    case Rejected = 'rejected';
    case ManuallyOverridden = 'manually_overridden';
    case RequiresReview = 'requires_review';
    case ManuallyVerified = 'manually_verified';
    case RateLimited = 'rate_limited';
}
