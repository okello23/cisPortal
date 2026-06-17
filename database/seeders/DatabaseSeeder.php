<?php

namespace Database\Seeders;

use App\Models\ClosureReason;
use App\Models\Department;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\ResolutionCategory;
use App\Models\SupportModule;
use App\Models\SupportSystem;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $regions = collect([
            ['name' => 'Central', 'code' => 'central'],
            ['name' => 'Eastern', 'code' => 'eastern'],
            ['name' => 'Northern', 'code' => 'northern'],
            ['name' => 'Western', 'code' => 'western'],
            ['name' => 'West Nile', 'code' => 'west-nile'],
            ['name' => 'Karamoja', 'code' => 'karamoja'],
        ])->map(fn ($region, $index) => [...$region, 'sort_order' => $index + 1]);

        foreach ($regions as $region) {
            Region::query()->updateOrCreate(['code' => $region['code']], $region);
        }

        foreach ([
            ['name' => 'RDS', 'code' => 'rds', 'sort_order' => 1],
            ['name' => 'ALIS', 'code' => 'alis', 'sort_order' => 2],
            ['name' => 'LabSpar', 'code' => 'labspar', 'sort_order' => 3],
            ['name' => 'Inventory', 'code' => 'inventory', 'sort_order' => 4],
            ['name' => 'Equipment Management', 'code' => 'equipment', 'sort_order' => 5],
        ] as $system) {
            SupportSystem::query()->updateOrCreate(['code' => $system['code']], $system);
        }

        foreach ([
            ['system' => 'rds', 'name' => 'Results', 'code' => 'results'],
            ['system' => 'rds', 'name' => 'Samples', 'code' => 'samples'],
            ['system' => 'alis', 'name' => 'Registration', 'code' => 'registration'],
            ['system' => 'inventory', 'name' => 'Stock Ledger', 'code' => 'stock-ledger'],
        ] as $module) {
            $systemId = SupportSystem::query()->where('code', $module['system'])->value('id');
            SupportModule::query()->updateOrCreate(
                ['system_id' => $systemId, 'code' => $module['code']],
                ['name' => $module['name'], 'sort_order' => 1]
            );
        }

        foreach ([
            ['name' => 'ICT Issue', 'code' => 'ict-issue'],
            ['name' => 'Bug Report', 'code' => 'bug-report'],
            ['name' => 'Change Request', 'code' => 'change-request'],
            ['name' => 'Access Request', 'code' => 'access-request'],
            ['name' => 'System Downtime', 'code' => 'system-downtime'],
            ['name' => 'Network/Connectivity Issue', 'code' => 'network-connectivity'],
            ['name' => 'Equipment Issue', 'code' => 'equipment-issue'],
            ['name' => 'Other', 'code' => 'other'],
        ] as $index => $issueType) {
            IssueType::query()->updateOrCreate(['code' => $issueType['code']], [...$issueType, 'sort_order' => $index + 1]);
        }

        foreach ([
            ['name' => 'Low', 'code' => 'low', 'sla_hours' => 72],
            ['name' => 'Medium', 'code' => 'medium', 'sla_hours' => 48],
            ['name' => 'High', 'code' => 'high', 'sla_hours' => 24],
            ['name' => 'Critical', 'code' => 'critical', 'sla_hours' => 8],
        ] as $index => $priorityLevel) {
            PriorityLevel::query()->updateOrCreate(['code' => $priorityLevel['code']], [...$priorityLevel, 'sort_order' => $index + 1]);
        }

        foreach ([
            ['name' => 'New', 'code' => 'new', 'color' => 'secondary'],
            ['name' => 'Assigned', 'code' => 'assigned', 'color' => 'primary'],
            ['name' => 'In Progress', 'code' => 'in_progress', 'color' => 'info'],
            ['name' => 'Pending User', 'code' => 'pending_user', 'color' => 'warning'],
            ['name' => 'Resolved', 'code' => 'resolved', 'color' => 'success'],
            ['name' => 'Closed', 'code' => 'closed', 'color' => 'dark'],
            ['name' => 'Reopened', 'code' => 'reopened', 'color' => 'danger'],
        ] as $index => $status) {
            TicketStatus::query()->updateOrCreate(['code' => $status['code']], [...$status, 'sort_order' => $index + 1]);
        }

        foreach ([
            ['name' => 'Training / User Guidance', 'code' => 'training'],
            ['name' => 'Configuration Change', 'code' => 'configuration-change'],
            ['name' => 'Bug Fix', 'code' => 'bug-fix'],
        ] as $item) {
            ResolutionCategory::query()->updateOrCreate(['code' => $item['code']], $item);
        }

        foreach ([
            ['name' => 'Resolved and confirmed', 'code' => 'resolved-confirmed'],
            ['name' => 'Duplicate issue', 'code' => 'duplicate-issue'],
            ['name' => 'No user response', 'code' => 'no-user-response'],
        ] as $item) {
            ClosureReason::query()->updateOrCreate(['code' => $item['code']], $item);
        }

        foreach ([
            ['name' => 'ICT Department', 'code' => 'ict'],
            ['name' => 'Laboratory Services', 'code' => 'laboratory'],
            ['name' => 'Data Management', 'code' => 'data-management'],
        ] as $item) {
            Department::query()->updateOrCreate(['code' => $item['code']], $item);
        }

        foreach ([
            ['name' => 'Central Public Health Laboratories', 'code' => 'cphl-hq', 'region_code' => 'central'],
            ['name' => 'Mbale RRH', 'code' => 'mbale-rrh', 'region_code' => 'eastern'],
            ['name' => 'Arua RRH', 'code' => 'arua-rrh', 'region_code' => 'west-nile'],
        ] as $item) {
            Facility::query()->updateOrCreate(
                ['code' => $item['code']],
                ['name' => $item['name'], 'region_id' => Region::query()->where('code', $item['region_code'])->value('id')]
            );
        }

        User::query()->updateOrCreate(['email' => 'admin@cphl.go.ug'], [
            'name' => 'CIS Administrator',
            'phone' => '0700000000',
            'role' => 'ict_admin',
            'active' => true,
            'password' => Hash::make('password123'),
        ]);

        User::query()->updateOrCreate(['email' => 'supervisor@cphl.go.ug'], [
            'name' => 'ICT Supervisor',
            'phone' => '0700000001',
            'role' => 'ict_supervisor',
            'active' => true,
            'password' => Hash::make('password123'),
        ]);

        User::query()->updateOrCreate(['email' => 'support@cphl.go.ug'], [
            'name' => 'ICT Support Staff',
            'phone' => '0700000002',
            'role' => 'ict_support_staff',
            'active' => true,
            'password' => Hash::make('password123'),
        ]);
    }
}
