<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Invoice = 'invoice';
    case BillOfLading = 'bill_of_lading';
    case CustomsDeclaration = 'customs_declaration';
    case Contract = 'contract';
    case IdDocument = 'id_document';
    case Other = 'other';
}
