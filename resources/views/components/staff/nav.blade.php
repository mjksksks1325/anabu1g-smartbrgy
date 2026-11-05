{{--
    Staff navigation: the one definition of every staff menu item, its permission rule, destination and
    current-page rule. Each layout keeps its own <nav> container and styling hooks and renders these sections inside it.

    variant="pages"      Layouts that load a page per item (IoT/request pages, account settings): wire:navigate links,
                         current page from the route.
    variant="workspace"  The single-page staff workspace: items that are workspace screens switch in place through
                         showScreen() (admin.js reads data-screen, data-perm and #cert-nav-badge); the rest are links.

    Hiding an item is presentation only. Every destination keeps its own route middleware, gates and policies.
--}}
@props([
    'variant' => 'pages',
    'activeScreen' => null,
])

@php
    $user = auth()->user();
    $isAdministrator = $user->hasAdministrativeAccess();
    $isPersonnelRole = in_array($user->role, ['admin', 'captain', 'staff'], true);
    $accountRoutes = ['profile.*', 'security.*', 'appearance.*', 'settings.*'];

    $sections = [
        [
            'label' => 'Overview',
            'visible' => $isPersonnelRole && $user->hasAnyPermission(['dashboard.view', 'demographics.view']),
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'dashboard', 'visible' => $user->hasAnyPermission(['dashboard.view']), 'route' => $isAdministrator ? 'admin.dashboard' : 'staff.dashboard', 'current' => ['admin.dashboard', 'staff.dashboard'], 'screen' => 'dashboard', 'perm' => 'Dashboard'],
                ['label' => 'Demographics', 'icon' => 'people', 'visible' => $user->hasAnyPermission(['demographics.view']), 'route' => 'staff.demographics', 'current' => ['staff.demographics'], 'screen' => 'demographics', 'perm' => 'Records'],
            ],
        ],
        [
            'label' => 'Records',
            'visible' => $isPersonnelRole && $user->hasAnyPermission(['records.view', 'households.view', 'voters.view', 'documents.view', 'eligibility.view', 'incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']),
            'items' => [
                // Household-only staff open the households list, which the workspace treats as its Records screen.
                ['label' => 'Households', 'icon' => 'people', 'visible' => $user->hasPermission('households.view') && ! $user->hasPermission('records.view'), 'route' => 'staff.households.page', 'current' => ['staff.households.*'], 'dataScreen' => 'records'],
                ['label' => 'Resident Records', 'icon' => 'document', 'visible' => $user->hasAnyPermission(['records.view']), 'route' => 'staff.residents.index', 'current' => ['staff.residents.*', 'staff.resident-applications.*'], 'screen' => 'records', 'perm' => 'Records'],
                ['label' => 'Voters', 'icon' => 'voters', 'visible' => $user->hasAnyPermission(['voters.view']), 'route' => 'staff.voters', 'current' => ['staff.voters', 'staff.voter-registrations.*'], 'screen' => 'voters', 'perm' => 'Records'],
                ['label' => 'Certificates & Clearances', 'icon' => 'certificate', 'visible' => $user->hasAnyPermission(['documents.view']), 'route' => 'staff.document-requests.index', 'current' => ['staff.document-requests.*', 'staff.issued-certificates.*'], 'screen' => 'certificates', 'perm' => 'Certificates', 'badge' => 'cert-nav-badge'],
                ['label' => 'Request Eligibility', 'icon' => 'clipboard', 'visible' => $user->hasAnyPermission(['eligibility.view', 'documents.view']), 'route' => 'staff.request-eligibility', 'current' => ['staff.request-eligibility'], 'screen' => 'request-records', 'perm' => 'Requests'],
                ['label' => 'Incident Reports', 'icon' => 'alert', 'visible' => $user->hasAnyPermission(['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']), 'route' => 'staff.incidents.index', 'current' => ['staff.incidents.*'], 'screen' => 'incidents', 'perm' => 'Incidents'],
            ],
        ],
        [
            'label' => 'Public Information',
            'visible' => $user->hasPermission('announcements.view'),
            'items' => [
                ['label' => 'Announcements', 'icon' => 'announcement', 'visible' => true, 'route' => 'staff.announcements.index', 'current' => ['staff.announcements.*']],
            ],
        ],
        [
            'label' => 'IoT Security',
            'visible' => $user->canTrackRfidFiles(),
            'items' => [
                ['label' => 'RFID File Tracking', 'icon' => 'card', 'visible' => $user->hasAnyPermission(['rfid.view']), 'route' => 'staff.rfid-files.index', 'current' => ['staff.rfid-files.*']],
            ],
        ],
        [
            'label' => 'Administration',
            'visible' => $isAdministrator,
            'items' => [
                ['label' => 'Smart Cabinet', 'icon' => 'cabinet', 'visible' => true, 'route' => 'admin.smart-cabinet.index', 'current' => ['admin.smart-cabinet.*']],
                ['label' => 'Employee Cabinet Access', 'icon' => 'key', 'visible' => true, 'route' => 'admin.cabinet-access.index', 'current' => ['admin.cabinet-access.*']],
                ['label' => 'Audit Log', 'icon' => 'audit', 'visible' => true, 'route' => 'admin.audit', 'current' => ['admin.audit', 'admin.audit.*'], 'screen' => 'audit', 'perm' => 'Audit'],
                ['label' => 'User Management', 'icon' => 'user', 'visible' => true, 'route' => 'admin.users.index', 'current' => ['admin.users.*'], 'screen' => 'users', 'perm' => 'Users'],
                ['label' => 'Settings', 'icon' => 'settings', 'visible' => true, 'route' => 'admin.settings', 'current' => ['admin.settings', ...$accountRoutes], 'screen' => 'settings', 'perm' => 'Settings'],
            ],
        ],
        [
            'label' => null,
            'class' => 'staff-settings-account-links',
            'visible' => true,
            'items' => [
                // The workspace opens account settings with a full page load, as before.
                ['label' => 'Settings', 'icon' => 'settings', 'visible' => ! $isAdministrator, 'route' => 'profile.edit', 'current' => $accountRoutes, 'fullPageLoad' => $variant === 'workspace'],
                ['label' => __('Resident Portal'), 'icon' => 'globe', 'visible' => true, 'route' => 'home', 'external' => true],
            ],
        ],
    ];
@endphp

@foreach ($sections as $section)
    @continue(! $section['visible'])
    <div class="{{ trim('sidebar-sec '.($section['class'] ?? '')) }}">
        @if ($section['label'])
            <div class="sidebar-label">{{ $section['label'] }}</div>
        @endif
        @foreach ($section['items'] as $item)
            @continue(! $item['visible'])
            @if ($item['external'] ?? false)
                <a class="nav-item portal-link staff-settings-portal" href="{{ route($item['route']) }}" target="_blank" rel="noopener" title="Opens the public Resident Portal in a new tab"><x-staff-icon :name="$item['icon']" />{{ $item['label'] }}<span class="sr-only"> (opens in a new tab)</span></a>
            @elseif ($variant === 'workspace' && isset($item['screen']))
                @php($isCurrent = $activeScreen === $item['screen'])
                <a @class(['nav-item', 'active' => $isCurrent]) onclick="showScreen('{{ $item['screen'] }}',this);return false" data-perm="{{ $item['perm'] }}" data-screen="{{ $item['screen'] }}" aria-current="{{ $isCurrent ? 'page' : 'false' }}" href="{{ route($item['route']) }}"><x-staff-icon :name="$item['icon']" />{{ $item['label'] }}@isset($item['badge']) <span class="nav-badge" id="{{ $item['badge'] }}" style="display:none;">0</span>@endisset</a>
            @elseif ($variant === 'workspace')
                <a @if (! ($item['fullPageLoad'] ?? false)) wire:navigate.hover @endif class="nav-item" @isset($item['dataScreen']) data-screen="{{ $item['dataScreen'] }}" @endisset aria-current="false" href="{{ route($item['route']) }}"><x-staff-icon :name="$item['icon']" />{{ $item['label'] }}</a>
            @else
                @php($isCurrent = request()->routeIs(...$item['current']))
                <a wire:navigate.hover @class(['nav-item', 'current active' => $isCurrent]) @isset($item['dataScreen']) data-screen="{{ $item['dataScreen'] }}" @endisset aria-current="{{ $isCurrent ? 'page' : 'false' }}" href="{{ route($item['route']) }}"><x-staff-icon :name="$item['icon']" />{{ $item['label'] }}</a>
            @endif
        @endforeach
    </div>
@endforeach
