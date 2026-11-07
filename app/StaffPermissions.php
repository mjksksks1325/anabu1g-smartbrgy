<?php

namespace App;

use App\Models\User;

class StaffPermissions
{
    /** @return array<string, array{label: string, actions: array<string, string>}> */
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'actions' => ['view' => 'Access page']],
            'demographics' => ['label' => 'Demographics (read only)', 'actions' => ['view' => 'Population statistics only (no individual records)']],
            'rfid' => ['label' => 'RFID File Tracking', 'actions' => ['view' => 'View file movements']],
            'records' => ['label' => 'Resident Records', 'actions' => ['view' => 'View records', 'create' => 'Register', 'update' => 'Edit / upload photo', 'archive' => 'Archive / restore', 'import' => 'Import profiling', 'export' => 'Export', 'activate' => 'Issue portal activation', 'review' => 'Review / approve new resident applications']],
            'households' => ['label' => 'Households', 'actions' => ['view' => 'View households', 'create' => 'Create', 'update' => 'Edit / assign members', 'remove' => 'Remove members']],
            'documents' => ['label' => 'Document Requests & Certificates', 'actions' => ['view' => 'View requests and certificate history', 'process' => 'Process / reject requests', 'issue' => 'Issue certificates', 'print' => 'Print / reprint', 'revoke' => 'Revoke issued certificates']],
            'announcements' => ['label' => 'Barangay Announcements', 'actions' => ['view' => 'View announcements', 'create' => 'Create drafts', 'update' => 'Edit announcements', 'publish' => 'Publish / unpublish', 'archive' => 'Archive / restore']],
            'eligibility' => ['label' => 'Request Eligibility', 'actions' => ['view' => 'View eligibility', 'export' => 'Export request records']],
            'voters' => ['label' => 'Voter Records', 'actions' => ['view' => 'View voters', 'create' => 'Register', 'update' => 'Edit', 'import' => 'Import CSV', 'export' => 'Export']],
            'incidents' => ['label' => 'Ordinary Incidents', 'actions' => ['view' => 'Review / case history', 'submit' => 'Submit incident', 'update' => 'Edit / manage cases', 'archive' => 'Archive']],
            'vawc' => ['label' => 'Restricted VAWC / BPO', 'actions' => ['view' => 'View restricted cases and BPOs', 'submit' => 'Create restricted case / BPO', 'update' => 'Manage restricted case / BPO', 'archive' => 'Archive restricted case']],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::modules() as $module => $definition) {
            foreach ($definition['actions'] as $action => $label) {
                $keys[] = $module.'.'.$action;
            }
        }

        return $keys;
    }

    /** @return array<string, list<string>> */
    public static function presets(): array
    {
        $secretary = ['dashboard.view', 'demographics.view', 'rfid.view'];
        foreach (['records', 'households', 'documents', 'announcements', 'voters', 'eligibility'] as $module) {
            foreach (self::modules()[$module]['actions'] as $action => $label) {
                $secretary[] = $module.'.'.$action;
            }
        }
        // Revoking an issued certificate is granted deliberately by an administrator, never through a preset.
        $secretary = array_values(array_diff($secretary, ['documents.revoke']));

        // Presets only pre-tick checkboxes; saving stores plain keys, so changing a preset never changes an existing account.
        // Archive, import, export, portal activation and account work stay unticked: an administrator adds them deliberately.
        return [
            'Secretary / Assistant' => $secretary,
            'Records Clerk' => ['dashboard.view', 'demographics.view', 'records.view', 'records.create', 'records.update', 'records.review', 'households.view', 'households.create', 'households.update'],
            'Documents Officer' => ['dashboard.view', 'documents.view', 'documents.process', 'documents.issue', 'documents.print', 'eligibility.view'],
            'Kagawad — Ordinary Incidents' => ['dashboard.view', 'demographics.view', 'rfid.view', 'incidents.view', 'incidents.submit', 'incidents.update'],
            'VAW Desk — Restricted Cases' => ['dashboard.view', 'vawc.view', 'vawc.submit', 'vawc.update'],
            'Tanod' => ['incidents.submit'],
        ];
    }

    /** @return array<string, string> One line per preset, shown when it is chosen. */
    public static function presetDescriptions(): array
    {
        return [
            'Secretary / Assistant' => 'Every records, households, documents, announcements, voters and eligibility action, including import, export and publishing. Suits a one-clerk office.',
            'Records Clerk' => 'Register and update residents, review new resident applications, and manage households. No archive, import, export or portal activation.',
            'Documents Officer' => 'Process, issue and print certificates and check request eligibility. No resident editing or exports.',
            'Kagawad — Ordinary Incidents' => 'Review, submit and manage ordinary incident reports. No VAWC or BPO access.',
            'VAW Desk — Restricted Cases' => 'VAWC cases and BPOs only. Choose this only for personnel the barangay has formally designated; the preset itself grants no authority.',
            'Tanod' => 'Submit incident reports only. Cannot read cases.',
        ];
    }

    /** @return list<string> */
    public static function forRoute(string $name): array
    {
        $name = preg_replace('/^legacy\./', '', $name);
        $name = str_replace('admin.protection-orders.', 'staff.protection-orders.', $name);

        return match ($name) {
            'staff.dashboard', 'staff.dashboard.summary' => ['dashboard.view'],
            'staff.demographics' => ['demographics.view'],
            'staff.rfid-files.index' => ['rfid.view'],
            'staff.announcements.index' => ['announcements.view'],
            'staff.announcements.store' => ['announcements.create'],
            'staff.announcements.update' => ['announcements.update'],
            'staff.announcements.publish' => ['announcements.publish'],
            'staff.announcements.archive', 'staff.announcements.restore' => ['announcements.archive'],
            'staff.households.page' => ['households.view'],
            'staff.resident-applications.index', 'staff.resident-applications.update', 'staff.resident-applications.photo' => ['records.review'],
            'staff.residents.index' => ['records.view'],
            'staff.residents.show', 'staff.residents.photo' => ['records.view'],
            'staff.residents.store' => ['records.create'],
            'staff.residents.update', 'staff.residents.photo.store' => ['records.update'],
            'staff.residents.destroy', 'staff.residents.restore' => ['records.archive'],
            'staff.residents.export' => ['records.export'],
            'staff.residents.portal-activation' => ['records.activate'],
            'staff.resident-profiling-imports.preview', 'staff.resident-profiling-imports.review', 'staff.resident-profiling-imports.store' => ['records.import'],
            'staff.households.index', 'staff.households.show' => ['households.view', 'records.view'],
            'staff.households.store' => ['households.create'],
            'staff.households.update', 'staff.households.members.update' => ['households.update'],
            'staff.households.members.destroy' => ['households.remove'],
            'staff.puroks.index' => ['demographics.view', 'records.view', 'households.view', 'voters.view', 'documents.view'],
            'staff.puroks.store' => ['records.create'],
            'staff.voters', 'staff.voter-registrations.index' => ['voters.view'],
            'staff.voter-registrations.store' => ['voters.create'],
            'staff.voter-registrations.update' => ['voters.update'],
            'staff.voter-registrations.import', 'staff.voter-registrations.template' => ['voters.import'],
            'staff.voter-registrations.export' => ['voters.export'],
            'staff.document-requests.index', 'staff.document-requests.list', 'staff.document-requests.show', 'staff.document-requests.attachment', 'staff.document-requests.live', 'staff.issued-certificates.index' => ['documents.view'],
            'staff.document-requests.update-status' => ['documents.process'],
            'staff.issued-certificates.store', 'staff.document-requests.issue', 'staff.document-requests.certificate-preview',
            'staff.additional-certificates.index', 'staff.additional-certificates.create', 'staff.additional-certificates.preview', 'staff.additional-certificates.store' => ['documents.issue'],
            'staff.issued-certificates.print', 'staff.issued-certificates.photo' => ['documents.print'],
            'staff.issued-certificates.revoke' => ['documents.revoke'],
            'staff.request-eligibility', 'staff.request-records.index', 'staff.request-restrictions.index', 'staff.residents.eligibility' => ['eligibility.view', 'documents.view'],
            'staff.request-records.export' => ['eligibility.export'],
            'staff.incidents.index' => ['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit'],
            'staff.incidents.options' => ['incidents.view', 'vawc.view'],
            'staff.incidents.create', 'staff.incidents.confirmation', 'staff.incidents.store' => ['incidents.submit', 'vawc.submit'],
            'staff.incidents.show', 'staff.incidents.attachments.show' => ['incidents.view', 'vawc.view'],
            'staff.incidents.update' => ['incidents.update', 'vawc.update'],
            'staff.incidents.destroy' => ['incidents.archive', 'vawc.archive'],
            'staff.protection-orders.index', 'staff.protection-orders.show' => ['vawc.view'],
            'staff.protection-orders.store' => ['vawc.submit'],
            'staff.protection-orders.update' => ['vawc.update'],
            'staff.case-residents' => ['incidents.view', 'vawc.view', 'documents.view', 'voters.view', 'households.view'],
            default => [],
        };
    }

    public static function landing(User $user): string
    {
        if ($user->hasAdministrativeAccess()) {
            return 'admin.dashboard';
        }
        foreach (['dashboard.view' => 'staff.dashboard', 'demographics.view' => 'staff.demographics', 'records.view' => 'staff.residents.index', 'documents.view' => 'staff.document-requests.index', 'announcements.view' => 'staff.announcements.index', 'voters.view' => 'staff.voters', 'incidents.view' => 'staff.incidents.index', 'vawc.view' => 'staff.incidents.index', 'rfid.view' => 'staff.rfid-files.index', 'households.view' => 'staff.households.page', 'eligibility.view' => 'staff.request-eligibility', 'incidents.submit' => 'staff.incidents.index', 'vawc.submit' => 'staff.incidents.index'] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'staff.access-pending';
    }
}
