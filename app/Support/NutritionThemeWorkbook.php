<?php

namespace App\Support;

use App\Models\NutritionTask;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class NutritionThemeWorkbook
{
    public function render(Collection $rows): string
    {
        $data = [['Code', 'Theme', 'Theme Status', 'Not Started', 'Started', 'In Progress', 'Completed', 'Overdue', 'Assigned Members', 'Completion Rate (%)']];
        foreach ($rows as $row) {
            $data[] = [$row['code'], $row['name'], NutritionTask::STATUSES[$row['status']], $row['not_started'], $row['started'],
                $row['in_progress'], $row['completed'], $row['overdue'], implode(', ', $row['members']), $row['completion_rate']];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="10" customWidth="1"/><col min="2" max="2" width="65" customWidth="1"/><col min="3" max="8" width="16" customWidth="1"/><col min="9" max="9" width="45" customWidth="1"/><col min="10" max="10" width="23" customWidth="1"/></cols><sheetData>';
        foreach ($data as $i => $cells) {
            $number = $i + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($cells as $column => $value) {
                $ref = chr(65 + $column).$number;
                $style = $i === 0 ? ' s="1"' : '';
                $xml .= is_int($value) || is_float($value)
                    ? '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData><autoFilter ref="A1:J'.count($data).'"/></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'nutrition-themes-');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the themes workbook.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Nutrition Themes" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf xfId="0" fontId="1" applyFont="1" applyAlignment="1"><alignment wrapText="1"/></xf></cellXfs></styleSheet>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
