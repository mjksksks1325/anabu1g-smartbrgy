<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmartCabinetController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.iot.smart-cabinet', [
            'cabinets' => CabinetDevice::query()->with('readers')->orderBy('identifier')->paginate(15),
            'cabinetOptions' => CabinetDevice::query()->orderBy('name')->get(['id', 'name']),
            'files' => CabinetFile::query()
                ->with('cabinetDevice:id,identifier,name')
                ->when($request->query('folder_status') === 'archived', fn ($query) => $query->where('is_active', false), fn ($query) => $query->where('is_active', true))
                ->orderBy('file_reference')
                ->paginate(20, ['*'], 'folders_page')
                ->withQueryString(),
            'folderStatus' => $request->query('folder_status') === 'archived' ? 'archived' : 'active',
        ]);
    }
}
