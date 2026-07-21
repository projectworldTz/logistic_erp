<?php

namespace App\Enums;

enum EmployeeDocumentType: string
{
    case EmploymentContract = 'employment_contract';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case AcademicCertificate = 'academic_certificate';
    case ProfessionalCertificate = 'professional_certificate';
    case DrivingLicense = 'driving_license';
    case WorkPermit = 'work_permit';
    case MedicalCertificate = 'medical_certificate';
    case TaxDocument = 'tax_document';
    case PensionRegistration = 'pension_registration';
    case BankInformation = 'bank_information';
    case WarningLetter = 'warning_letter';
    case PromotionLetter = 'promotion_letter';
    case TrainingCertificate = 'training_certificate';
    case Other = 'other';
}
