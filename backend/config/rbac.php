<?php

return [

    /*
     * Global (platform-level) roles. These get tenant_id = null and are
     * assigned to platform staff, not company users.
     */
    'global_roles' => [
        'Super Admin',
        'Support Admin',
    ],

    /*
     * Roles provisioned into every new tenant. Each tenant gets its own
     * copies of these roles (team-scoped via tenant_id) so future
     * per-tenant customization is possible without touching other tenants.
     */
    'tenant_roles' => [
        'Company Owner',
        'Company Admin',
        'Branch Manager',
        'Operations Manager',
        'Clearing Officer',
        'Forwarding Officer',
        'Warehouse Manager',
        'Warehouse Staff',
        'Dispatcher',
        'Fleet Manager',
        'Driver',
        'Finance Manager',
        'Accountant',
        'Sales Manager',
        'Sales Executive',
        'Customer Service',
        'Document Controller',
        'Auditor',
        'Customer Portal User',
    ],

    /*
     * Permission catalog, grouped by module. Each future ERP module
     * (crm, shipments, clearing, ...) adds its own entry here without
     * touching this structure.
     */
    'permissions' => [
        'platform' => [
            'platform.tenants.view' => 'View tenants',
            'platform.tenants.manage' => 'Suspend / activate tenants',
            'platform.plans.view' => 'View subscription plans',
            'platform.plans.manage' => 'Create / edit / delete subscription plans',
            'platform.subscriptions.view' => 'View tenant subscriptions',
            'platform.metrics.view' => 'View platform-wide metrics',
            'platform.audit.view' => 'View platform audit log',
            'platform.support.manage' => 'Manage support tickets',
            'platform.landing_content.view' => 'View landing page content',
            'platform.landing_content.manage' => 'Edit landing page content',
        ],
        'core' => [
            'core.dashboard.view' => 'View tenant dashboard',
            'core.company.view' => 'View company profile',
            'core.company.manage' => 'Edit company profile',
            'core.branches.view' => 'View branches',
            'core.branches.manage' => 'Create / edit branches',
            'core.users.view' => 'View company users',
            'core.users.manage' => 'Invite / edit / deactivate company users',
            'core.audit.view' => 'View company audit log',
        ],
        'crm' => [
            'crm.leads.view' => 'View leads',
            'crm.leads.manage' => 'Create / edit / delete / convert leads',
            'crm.customers.view' => 'View customers',
            'crm.customers.manage' => 'Create / edit / delete customers',
            'crm.contacts.view' => 'View customer contacts',
            'crm.contacts.manage' => 'Create / edit / delete customer contacts',
        ],
        'clearing' => [
            'clearing.files.view' => 'View clearing files',
            'clearing.files.manage' => 'Create / edit / delete clearing files',
        ],
        'freight' => [
            'freight.bookings.view' => 'View freight bookings',
            'freight.bookings.manage' => 'Create / edit / delete freight bookings',
        ],
        'containers' => [
            'containers.items.view' => 'View containers',
            'containers.items.manage' => 'Create / edit / delete containers',
        ],
        'warehouse' => [
            'warehouse.items.view' => 'View warehouse items',
            'warehouse.items.manage' => 'Create / edit / delete warehouse items',
        ],
        'fleet' => [
            'fleet.vehicles.view' => 'View fleet vehicles',
            'fleet.vehicles.manage' => 'Create / edit / delete fleet vehicles',
        ],
        'finance' => [
            'finance.invoices.view' => 'View invoices',
            'finance.invoices.manage' => 'Create / edit / delete invoices',
        ],
        'accounting' => [
            'accounting.accounts.view' => 'View chart of accounts',
            'accounting.accounts.manage' => 'Create / edit / delete accounts',
            'accounting.journal.view' => 'View journal entries',
            'accounting.journal.manage' => 'Create / edit / delete draft journal entries',
            'accounting.journal.post' => 'Post / void journal entries',
        ],
        'documents' => [
            'documents.files.view' => 'View documents',
            'documents.files.manage' => 'Upload / delete documents',
        ],
        'reports' => [
            'reports.view' => 'View cross-module reports',
        ],
        'analytics' => [
            'analytics.view' => 'View operational & financial analytics',
        ],
        'quotations' => [
            'quotations.items.view' => 'View quotations',
            'quotations.items.manage' => 'Create / edit / delete quotations',
        ],
        'shipments' => [
            'shipments.items.view' => 'View shipments',
            'shipments.items.manage' => 'Create / edit / delete shipments',
        ],
        'portal' => [
            'portal.access' => 'Access the client portal (own company data only)',
            'portal.documents.upload' => 'Upload documents via the client portal',
            'portal.quotations.approve' => 'Approve / reject quotations via the client portal',
            'portal.messages.send' => 'Send messages via the client portal',
        ],
        'demurrage' => [
            'demurrage.rate_cards.view' => 'View demurrage rate cards',
            'demurrage.rate_cards.manage' => 'Create / edit / delete demurrage rate cards',
            'demurrage.charges.view' => 'View demurrage dashboard & charge history',
            'demurrage.charges.manage' => 'Calculate / waive / invoice demurrage charges',
        ],
    ],

    /*
     * Default permissions granted to each role at seed/provisioning time.
     * Wildcards ('platform.*', 'core.*') expand to every permission in
     * that module's catalog above.
     */
    'default_role_permissions' => [
        'Super Admin' => ['platform.*'],
        'Support Admin' => [
            'platform.tenants.view',
            'platform.support.manage',
            'platform.audit.view',
            'platform.metrics.view',
        ],

        'Company Owner' => ['core.*', 'crm.*', 'clearing.*', 'freight.*', 'containers.*', 'warehouse.*', 'fleet.*', 'finance.*', 'accounting.*', 'documents.*', 'reports.*', 'quotations.*', 'shipments.*', 'analytics.*', 'demurrage.*'],
        'Company Admin' => ['core.*', 'crm.*', 'clearing.*', 'freight.*', 'containers.*', 'warehouse.*', 'fleet.*', 'finance.*', 'accounting.*', 'documents.*', 'reports.*', 'quotations.*', 'shipments.*', 'analytics.*', 'demurrage.*'],
        'Branch Manager' => ['core.dashboard.view', 'core.branches.view', 'core.users.view'],
        'Operations Manager' => ['core.dashboard.view', 'core.branches.view', 'clearing.files.view', 'freight.bookings.view', 'containers.items.view', 'warehouse.items.view', 'fleet.vehicles.view', 'finance.invoices.view', 'accounting.accounts.view', 'accounting.journal.view', 'documents.files.view', 'reports.view', 'quotations.items.view', 'shipments.*', 'analytics.view', 'demurrage.charges.view', 'demurrage.charges.manage'],
        'Clearing Officer' => ['core.dashboard.view', 'clearing.files.view', 'clearing.files.manage', 'containers.items.view', 'containers.items.manage', 'shipments.items.view', 'demurrage.charges.view', 'demurrage.charges.manage'],
        'Forwarding Officer' => ['core.dashboard.view', 'freight.bookings.view', 'freight.bookings.manage', 'containers.items.view', 'containers.items.manage', 'shipments.items.view'],
        'Warehouse Manager' => ['core.dashboard.view', 'containers.items.view', 'containers.items.manage', 'warehouse.items.view', 'warehouse.items.manage', 'demurrage.charges.view', 'demurrage.charges.manage'],
        'Warehouse Staff' => ['core.dashboard.view', 'containers.items.view', 'containers.items.manage', 'warehouse.items.view', 'warehouse.items.manage'],
        'Dispatcher' => ['core.dashboard.view', 'fleet.vehicles.view'],
        'Fleet Manager' => ['core.dashboard.view', 'fleet.vehicles.view', 'fleet.vehicles.manage'],
        'Driver' => ['core.dashboard.view', 'fleet.vehicles.view'],
        'Finance Manager' => ['core.dashboard.view', 'finance.invoices.view', 'finance.invoices.manage', 'accounting.*', 'reports.view', 'analytics.view', 'demurrage.*'],
        'Accountant' => ['core.dashboard.view', 'finance.invoices.view', 'finance.invoices.manage', 'accounting.accounts.view', 'accounting.accounts.manage', 'accounting.journal.view', 'accounting.journal.manage'],
        'Sales Manager' => ['core.dashboard.view', 'crm.*', 'quotations.*'],
        'Sales Executive' => [
            'core.dashboard.view',
            'crm.leads.view', 'crm.leads.manage',
            'crm.customers.view', 'crm.customers.manage',
            'crm.contacts.view', 'crm.contacts.manage',
            'quotations.items.view', 'quotations.items.manage',
        ],
        'Customer Service' => [
            'core.dashboard.view',
            'crm.leads.view',
            'crm.customers.view',
            'crm.contacts.view', 'crm.contacts.manage',
            'quotations.items.view',
        ],
        'Document Controller' => ['core.dashboard.view', 'documents.files.view', 'documents.files.manage'],
        'Auditor' => [
            'core.dashboard.view',
            'core.audit.view',
            'core.company.view',
            'core.branches.view',
            'core.users.view',
            'crm.leads.view',
            'crm.customers.view',
            'crm.contacts.view',
            'clearing.files.view',
            'freight.bookings.view',
            'containers.items.view',
            'warehouse.items.view',
            'fleet.vehicles.view',
            'finance.invoices.view',
            'accounting.accounts.view',
            'accounting.journal.view',
            'documents.files.view',
            'reports.view',
            'quotations.items.view',
            'shipments.items.view',
            'analytics.view',
            'demurrage.rate_cards.view',
            'demurrage.charges.view',
        ],
        'Customer Portal User' => ['portal.access', 'portal.documents.upload', 'portal.quotations.approve', 'portal.messages.send'],
    ],
];
