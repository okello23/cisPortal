<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\ManagerDashboardService;
use App\Support\SupportPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly SupportPerformanceService $supportPerformanceService,
        private readonly ManagerDashboardService $managerDashboardService,
    )
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $canViewManagerDashboard = $user->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER]);
        $performanceFilters = [
            'staff_id' => $request->integer('performance_staff_id') ?: null,
        ];
        $query = Ticket::query()->with(['status', 'assignedStaff', 'system']);

        if (! $user->hasAnyRole(['ict_admin', 'ict_manager', 'ict_supervisor'])) {
            $query->where('assigned_to', $user->id);
        }

        $requestedTab = $request->string('tab')->toString();
        $activeTab = match (true) {
            $requestedTab === 'performance' => 'performance',
            $requestedTab === 'manager' && $canViewManagerDashboard => 'manager',
            default => 'overview',
        };

        return view('admin.dashboard', [
            'activeTab' => $activeTab,
            'canViewManagerDashboard' => $canViewManagerDashboard,
            'tickets' => $query->latest()->limit(12)->get(),
            'staff' => User::query()->where('active', true)->orderBy('name')->get(),
            'metrics' => [
                'assigned' => Ticket::query()->whereNotNull('assigned_to')->count(),
                'in_progress' => Ticket::query()->whereHas('status', fn ($status) => $status->where('code', 'in_progress'))->count(),
                'resolved' => Ticket::query()->whereHas('status', fn ($status) => $status->where('code', 'resolved'))->count(),
                'sla_compliance' => Ticket::query()
                    ->whereNotNull('resolved_at')
                    ->whereRaw('date(resolved_at) <= expected_resolution_date')
                    ->count(),
            ],
            'performance' => $this->supportPerformanceService->buildForUser($user, $performanceFilters),
            'managerDashboard' => $canViewManagerDashboard ? $this->managerDashboardService->build($request) : null,
        ]);
    }

    public function exportPerformance(Request $request, string $format): Response
    {
        $user = Auth::user();
        $dataset = $this->supportPerformanceService->buildForUser($user, [
            'staff_id' => $request->integer('performance_staff_id') ?: null,
        ]);
        $rows = $this->supportPerformanceService->exportRows($dataset);
        $stamp = now()->format('Ymd_His');

        return match (strtolower($format)) {
            'csv' => response($this->toDelimited($rows, ','), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"support-team-performance_{$stamp}.csv\"",
            ]),
            'xls' => response($this->toExcelHtml($rows, $dataset), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"support-team-performance_{$stamp}.xls\"",
            ]),
            'pdf' => response($this->toSimplePdf($rows, $dataset), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"support-team-performance_{$stamp}.pdf\"",
            ]),
            default => abort(404),
        };
    }

    private function toDelimited($rows, string $delimiter): string
    {
        $handle = fopen('php://temp', 'r+');
        $allRows = $rows->values();

        if ($allRows->isEmpty()) {
            fputcsv($handle, ['No data available'], $delimiter);
        } else {
            fputcsv($handle, array_keys($allRows->first()), $delimiter);

            foreach ($allRows as $row) {
                fputcsv($handle, array_values($row), $delimiter);
            }
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    private function toExcelHtml($rows, array $dataset): string
    {
        $headers = $rows->isNotEmpty() ? array_keys($rows->first()) : [];

        $html = '<table border="1"><tr>';
        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }
        $html .= '</tr>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>'.e((string) $value).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        return '<html><body><h2>Support Team Performance</h2><p>Generated '.e(now()->format('d M Y H:i')).'</p>'.$html.'</body></html>';
    }

    private function toSimplePdf($rows, array $dataset): string
    {
        $lines = [
            'Support Team Performance Dashboard',
            'Generated '.now()->format('d M Y H:i'),
            $dataset['is_manager_scope'] ? 'Scope: all support staff' : 'Scope: individual staff',
            str_repeat('-', 110),
        ];

        $headers = ['Staff', 'Assigned', 'Resolved', 'Closed', 'Escalated', 'First Resp', 'Resolution', 'SLA %', 'Rating', 'Reopened', 'Backlog', 'Active'];
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', 110);

        foreach ($rows as $row) {
            $lines[] = implode(' | ', [
                $this->truncate((string) $row['Support Staff'], 18),
                $row['Tickets Assigned'],
                $row['Tickets Resolved'],
                $row['Tickets Closed'],
                $row['Tickets Escalated'],
                $row['Average First Response Time (hrs)'],
                $row['Average Resolution Time (hrs)'],
                $row['SLA Compliance Rate (%)'],
                $row['Average Customer Rating'],
                $row['Reopened Tickets'],
                $row['Current Backlog'],
                $row['Active Tickets'],
            ]);
        }

        return $this->buildRawPdf($lines);
    }

    private function buildRawPdf(array $lines): string
    {
        $fontObject = 3;
        $perPage = 38;
        $lineHeight = 14;
        $pages = array_chunk($lines, $perPage);
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjectIds = [];
        $contentObjectIds = [];
        $objectId = 4;

        foreach ($pages as $index => $pageLines) {
            $content = "BT\n/F1 10 Tf\n40 800 Td\n";

            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $content .= "0 -{$lineHeight} Td\n";
                }

                $content .= '('.$this->escapePdfText($line).") Tj\n";
            }

            $content .= "ET";

            $contentObjectIds[$index] = $objectId++;
            $pageObjectIds[$index] = $objectId++;

            $objects[$contentObjectIds[$index]] = '<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream";
            $objects[$pageObjectIds[$index]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 '.$fontObject.' 0 R >> >> /Contents '.$contentObjectIds[$index].' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pageObjectIds).' /Kids ['.collect($pageObjectIds)->map(fn ($id) => $id.' 0 R')->implode(' ').'] >>';
        $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));

        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length - 3).'...';
    }
}
