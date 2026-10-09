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
            'demographics' => ['label' => 'Demographics (read only)', 'actions' => ['view' => 'Browse, search, filter and open details']],
            'rfid' => ['label' => 'RFID File Tracking', 'actions' => ['view' => 'View file movements']],
            'records' => ['label' => 'Resident Records', 'actions' => ['view' => 'View records', 'create' => 'Register', 'update' => 'Edit / upload photo', 'archive' => 'Archive / restore', 'import' => 'Import profiling', 'export' => 'Export', 'activate' => 'Issue portal activation']],
            'households' => ['label' => 'Households', 'actions' => ['view' => 'View households', 'create' => 'Create', 'update' => 'Edit / assign members', 'remove' => 'Remove members']],
            'documents' => ['label' => 'Document Requests & Certificates', 'actions' => ['view' => 'View requests and certificate history', 'process' => 'Process / reject requests', 'issue' => 'Issue certificates', 'print' => 'Print / reprint']],
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
        foreach (['records', 'households', 'documents', 'voters', 'eligibility'] as $module) {
            foreach (self::modules()[$module]['actions'] as $action => $label) {
                $secretary[] = $module.'.'.$action;
            }
        }

        return [
            'Secretary / Assistant' => $secretary,
            'Authorized Kagawad' => ['dashboard.view', 'demographics.view', 'rfid.view', 'incidents.view', 'incidents.submit', 'incidents.update', 'incidents.archive', 'vawc.view', 'vawc.submit', 'vawc.update', 'vawc.archive'],
            'Tanod' => ['incidents.submit'],
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
            'staff.households.page' => ['households.view'],
            'staff.residents.index' => ['records.view'],
            'staff.residents.show', 'staff.residents.photo' => ['records.view', 'demographics.view'],
            'staff.residents.store' => ['records.create'],
            'staff.residents.update', 'staff.residents.photo.store' => ['records.update'],
            'staff.residents.destroy', 'staff.residents.restore' => ['records.archive'],
            'staff.residents.export' => ['records.export'],
            'staff.residents.portal-activation' => ['records.activate'],
            'staff.resident-profiling-imports.preview', 'staff.resident-profiling-imports.review', 'staff.resident-profiling-imports.store' => ['records.import'],
            'staff.households.index', 'staff.households.show' => ['households.view', 'demographics.view', 'records.view'],
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
            'staff.issued-certificates.store', 'staff.document-requests.issue' => ['documents.issue'],
            'staff.issued-certificates.print', 'staff.issued-certificates.photo' => ['documents.print'],
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
        if ($user->isSuperAdmin()) {
            return 'admin.dashboard';
        }
        foreach (['dashboard.view' => 'staff.dashboard', 'demographics.view' => 'staff.demographics', 'records.view' => 'staff.residents.index', 'documents.view' => 'staff.document-requests.index', 'voters.view' => 'staff.voters', 'incidents.view' => 'staff.incidents.index', 'vawc.view' => 'staff.incidents.index', 'rfid.view' => 'staff.rfid-files.index', 'households.view' => 'staff.households.page', 'eligibility.view' => 'staff.request-eligibility', 'incidents.submit' => 'staff.incidents.index', 'vawc.submit' => 'staff.incidents.index'] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'staff.access-pending';
    }
}
