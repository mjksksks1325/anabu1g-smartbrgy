<?php

test('certificate lanes preserve their scroll position during live refreshes', function () {
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($adminScript)
        ->toContain("board.querySelectorAll('.cert-lane[data-lane-id]')")
        ->toContain('laneScrollPositions[laneElement.dataset.laneId] = laneElement.scrollTop;')
        ->toContain('laneEl.dataset.laneId = lane.id;')
        ->toContain('laneEl.scrollTop = savedScrollTop;');
});
