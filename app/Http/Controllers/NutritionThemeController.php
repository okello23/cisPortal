<?php

namespace App\Http\Controllers;

use App\Models\NutritionTheme;
use App\Models\User;
use App\Support\AuditService;
use App\Support\NutritionThemeReport;
use App\Support\NutritionThemeWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class NutritionThemeController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $theme = NutritionTheme::query()->create($this->validated($request));
        $this->auditService->log('nutrition_theme.created', $theme, null, $theme->toArray(), $request->user()->id, $request);

        return to_route('nutrition-tasks.dashboard', ['tab' => 'themes'])->with('status', 'Theme created. It is now available in both theme dropdowns.');
    }

    public function update(Request $request, NutritionTheme $nutritionTheme): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request, $nutritionTheme);
        $old = $nutritionTheme->toArray();
        $nutritionTheme->update($data);
        $this->auditService->log('nutrition_theme.updated', $nutritionTheme, $old, $nutritionTheme->toArray(), $request->user()->id, $request);

        return to_route('nutrition-tasks.dashboard', ['tab' => 'themes'])->with('status', 'Theme updated. Existing task links have been preserved.');
    }

    public function export(Request $request, NutritionThemeReport $report, NutritionThemeWorkbook $workbook): Response
    {
        $this->authorizeAdmin($request);

        return response($workbook->render($report->rows()), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="nutrition-themes-'.now()->format('Y-m-d').'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validated(Request $request, ?NutritionTheme $theme = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9]+[a-z]?$/', Rule::unique('nutrition_themes', 'code')->ignore($theme?->id)],
            'name' => ['required', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_TASK_ADMIN]), 403);
    }
}
