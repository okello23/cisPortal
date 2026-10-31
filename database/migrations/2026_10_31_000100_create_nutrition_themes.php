<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_themes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('name');
            $table->unsignedInteger('sort_order')->index();
            $table->timestamps();
        });

        // Only planned activity codes, descriptions and their original order are included.
        // The second 4c in the workplan was corrected to 4d with user approval.
        $activities = [
            ['1', 'Finalize the FY24/25 Annual Progress Report'],
            ['2', 'Submit the Quansys validation manuscript to peer review journal'],
            ['3', 'Develop a manuscript from UPHIA data'],
            ['3a', 'Develop a draft manuscript for UPHIA'],
            ['3b', 'Internal review of the draft manuscript'],
            ['3c', 'External review of the draft manuscript'],
            ['3d', 'Submit the UPHIA manuscript to peer review journal'],
            ['4', 'Protocol development, regulatory and ethical approvals-ANC and household validation survey'],
            ['4a', 'Draft protocol on ANC surveillance'],
            ['4b', 'Joint review of the ANC protocol'],
            ['4c', 'REC approval for the ANC protocol'],
            ['4d', 'UNSCT approval of the ANC protocol'],
            ['5', 'ANC sentinel site selection and household mapping'],
            ['6', 'MN surveillance trainers and health worker training'],
            ['7', 'Acquistion of remnant blood and urine from ANC clinics'],
            ['7a', 'Acquistion of whole blood and urine from community households'],
            ['8', 'Procurement of reagents and supplies'],
            ['9', 'Establish and optimize a protocol for urinary iodine Concetration'],
            ['9a', 'Verificational experiments'],
            ['9b', 'Develop SOPs for UIC'],
            ['10', 'Analyze samples from ANC and households'],
            ['10a', 'Analyze ANC samples for UIC'],
            ['10b', 'Analysis of ANC wholeblood and plasma sample'],
            ['11', 'Preliminary data analysis of ANC samples (demographics)'],
            ['12', 'Optimize Hb-DBS assay performance'],
            ['13', 'Procure CDC-EQA panels'],
            ['14', 'PhD Research Fellows Projects'],
            ['15', 'Train and mentor 2 Msc fellows'],
            ['16', 'Stakeholder engagement with infectious disease programs'],
            ['17', 'Annual progress report'],
        ];
        foreach ($activities as $index => [$code, $name]) {
            DB::table('nutrition_themes')->insert([
                'code' => $code, 'name' => $name, 'sort_order' => $index + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['nutrition_tasks', 'nutrition_subtasks'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('theme_id')->nullable()->constrained('nutrition_themes')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['nutrition_subtasks', 'nutrition_tasks'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('theme_id'));
        }
        Schema::dropIfExists('nutrition_themes');
    }
};
