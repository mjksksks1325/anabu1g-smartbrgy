<?php

use App\Models\User;

it('announces the first loading rows of the workspace lists as status messages', function () {
    $content = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.incidents.index'))->assertOk()->getContent();

    foreach (['Loading issued certificates...', 'Loading voter list...', 'Loading incident reports...'] as $message) {
        expect($content)->toContain('data-state="loading"><span role="status" aria-busy="true">'.$message.'</span>');
    }
});

it('spans the voter loading row across the Actions column only when the viewer has it', function (array $permissions, int $columns) {
    $content = $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => $permissions]))
        ->get(route('staff.voters'))->assertOk()->getContent();

    expect($content)->toContain('<tbody id="voter-registry-tbody"><tr class="workspace-state-row"><td colspan="'.$columns.'"');
})->with([
    'view only' => [['voters.view'], 7],
    'view and update' => [['voters.view', 'voters.update'], 8],
]);

it('loads the shared list styles on the workspace', function () {
    $css = file_get_contents(public_path('css/staff-theme.css'));

    expect($css)->toContain('#app.admin-interface .workspace-state[data-state="error"]')
        ->toContain('@media (min-width: 761px) and (max-width: 1100px)')
        ->toContain('#app.admin-interface .audit-table-head { display: none !important; }');
});
