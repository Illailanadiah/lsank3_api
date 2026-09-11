<?php

namespace App\Enums;

enum NotificationEvent: string
{
    // Applications
    case APPLICATION_NEW = 'application.new';
    case APPLICATION_RENEWAL = 'application.renewal';
    case APPLICATION_STATUS_CHANGED = 'application.status_changed';
    case APPLICATION_TASK_ASSIGNED = 'application.task_assigned';

    // Licences and security refunds
    case LICENSE_GENERATED = 'license.generated';
    case LICENSE_TERMINATION_REQUESTED =
        'license.termination_requested';
    case LICENSE_TERMINATION_STATUS_CHANGED =
        'license.termination_status_changed';
    case SECURITY_REFUND_REQUIRED =
        'security.refund_required';
    case SECURITY_REFUND_STATUS_CHANGED =
        'security.refund.status_changed';

    // Enforcement
    case COMPOUND_ISSUED = 'compound.issued';
    case NOTICE_ISSUED = 'notice.issued';

    // Legal
    case CIVIL_CASE_UPDATED = 'civil_case.updated';
    case CRIMINAL_CASE_UPDATED = 'criminal_case.updated';
}