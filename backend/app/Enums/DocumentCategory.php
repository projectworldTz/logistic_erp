<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Invoice = 'invoice';
    case BillOfLading = 'bill_of_lading';
    case CustomsDeclaration = 'customs_declaration';
    case Contract = 'contract';
    case IdDocument = 'id_document';
    case PackingList = 'packing_list';
    case CertificateOfOrigin = 'certificate_of_origin';
    case InsuranceCertificate = 'insurance_certificate';
    case DeliveryNote = 'delivery_note';
    case ReleaseOrder = 'release_order';
    case Other = 'other';
}
