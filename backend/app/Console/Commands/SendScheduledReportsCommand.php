<?php

namespace App\Console\Commands;

use App\Exports\ArrayExport;
use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use App\Models\Tenant;
use App\Services\Reports\ReportBuilder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class SendScheduledReportsCommand extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Email every due scheduled report (per its own frequency) to its configured recipients, across all tenants';

    public function handle(ReportBuilder $builder): int
    {
        $context = app(TenantContext::class);
        $sentCount = 0;

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $context->set($tenantId);

            $due = ScheduledReport::query()->get()->filter->isDue();

            foreach ($due as $report) {
                [$headings, $rows] = $builder->build($report->module);
                $writerType = $report->format === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV;
                $mimeType = $report->format === 'xlsx'
                    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    : 'text/csv';

                $content = Excel::raw(new ArrayExport($headings, $rows), $writerType);
                $fileName = "{$report->module}-".now()->format('Y-m-d').".{$report->format}";

                Mail::to($report->recipients)->send(new ScheduledReportMail(
                    reportName: $report->name,
                    module: $report->module,
                    fileContent: $content,
                    fileName: $fileName,
                    mimeType: $mimeType,
                ));

                $report->update(['last_sent_at' => now()]);
                $sentCount++;
            }

            $context->clear();
        }

        $this->info("Scheduled reports complete: {$sentCount} report(s) sent.");

        return self::SUCCESS;
    }
}
