<?php

namespace App\Enums;

enum DesignationCategory: string
{
    case Management = 'management';
    case ClearingAndCustoms = 'clearing_and_customs';
    case ForwardingAndLogistics = 'forwarding_and_logistics';
    case TransportAndFleet = 'transport_and_fleet';
    case WarehouseAndCargo = 'warehouse_and_cargo';
    case FinanceAndAccounts = 'finance_and_accounts';
    case SalesAndCrm = 'sales_and_crm';
    case AdministrationAndSupport = 'administration_and_support';
    case Other = 'other';
}
