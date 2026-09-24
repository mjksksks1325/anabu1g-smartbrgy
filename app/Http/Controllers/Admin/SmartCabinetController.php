<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use Illuminate\View\View;

class SmartCabinetController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.iot.smart-cabinet', [
            'cabinets' => CabinetDevice::query()->orderBy('identifier')->paginate(15),
        ]);
    }
}
