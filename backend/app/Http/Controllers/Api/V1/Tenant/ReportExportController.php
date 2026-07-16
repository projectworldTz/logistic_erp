<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function export(Request $request, string $module, ReportBuilder $builder)
    {
        abort_unless(array_key_exists($module, ReportBuilder::MODULE_PERMISSIONS), 404);
        abort_unless(Auth::user()->can(ReportBuilder::MODULE_PERMISSIONS[$module]), 403);

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $writerType = $format === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV;

        [$headings, $rows] = $builder->build($module);
        $filename = "{$module}-".now()->format('Y-m-d').".{$format}";

        return Excel::download(new ArrayExport($headings, $rows), $filename, $writerType);
    }
}
