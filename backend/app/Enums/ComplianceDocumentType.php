<?php

namespace App\Enums;

enum ComplianceDocumentType: string
{
    case BusinessRegistration = 'business_registration';
    case TaxCertificate = 'tax_certificate';
    case TradingLicense = 'trading_license';
    case AuthorizedSignatoryId = 'authorized_signatory_id';
    case Other = 'other';
}
