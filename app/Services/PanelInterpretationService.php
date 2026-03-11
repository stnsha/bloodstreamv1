<?php

namespace App\Services;

use App\Models\PanelInterpretation;
use App\Models\PanelPanelItem;

class PanelInterpretationService
{
    public function lipidInterpretation($cri_i = null, $cri_ii = null, $aip = null)
    {
        // Get panel_panel_item_ids (always needed)
        $criIId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->where('panel_items.code', 'CRI-I')
            ->value('panel_panel_items.id');

        $criIIId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->where('panel_items.code', 'CRI-II')
            ->value('panel_panel_items.id');

        $aipId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->where('panel_items.code', 'AIP')
            ->value('panel_panel_items.id');

        // If any param is null, return early with nulls for interpretations
        if (is_null($cri_i) || is_null($cri_ii) || is_null($aip)) {
            return [
                'cri_i_panel_panel_item_id' => $criIId,
                'cri_i_interpretation' => null,
                'cri_ii_panel_panel_item_id' => $criIIId,
                'cri_ii_interpretation' => null,
                'aip_panel_panel_item_id' => $aipId,
                'aip_interpretation' => null,
            ];
        }

        $cri_i_interpretation = null;
        $cri_ii_interpretation = null;
        $aip_interpretation = null;

        /**
         * CRI-I
         * Panel Item Name: Tot. Cholesterol / HDL Cholesterol
         * panel_interpretation_id = 1 - 3
         * panel_panel_item_id = 32
         */
        $cri_i = (float) $cri_i;
        $criInterpretations = PanelInterpretation::where('panel_panel_item_id', $criIId)->get();

        foreach ($criInterpretations as $criInt) {
            $range = trim($criInt->range);

            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($cri_i >= $value) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($cri_i <= $value) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($cri_i < $value) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($cri_i > $value) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($cri_i >= $min && $cri_i <= $max) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($cri_i >= $min && $cri_i <= $max) {
                    $cri_i_interpretation = $criInt->id;
                    break;
                }
            }
        }

        /**
         * CRI-II
         * Panel Item Name: LDL Cholesterol/HDL ratio
         * panel_interpretation_id = 4 - 6
         * panel_panel_item_id = 34
         */
        $cri_ii = (float) $cri_ii;
        $criInterpretations = PanelInterpretation::where('panel_panel_item_id', $criIIId)->get();

        foreach ($criInterpretations as $criInt) {
            $range = trim($criInt->range);

            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($cri_ii >= $value) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($cri_ii <= $value) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($cri_ii < $value) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($cri_ii > $value) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($cri_ii >= $min && $cri_ii <= $max) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($cri_ii >= $min && $cri_ii <= $max) {
                    $cri_ii_interpretation = $criInt->id;
                    break;
                }
            }
        }

        /**
         * AIP
         * Panel Item Name: Atherogenic Index of Plasma
         * panel_interpretation_id = 7 - 9
         * panel_panel_item_id = 33
         */
        $aip = (float) $aip;
        $aipInterpretations = PanelInterpretation::where('panel_panel_item_id', $aipId)->get();

        foreach ($aipInterpretations as $aipInt) {
            $range = trim($aipInt->range);

            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($aip >= $value) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($aip <= $value) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($aip < $value) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($aip > $value) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($aip >= $min && $aip <= $max) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($aip >= $min && $aip <= $max) {
                    $aip_interpretation = $aipInt->id;
                    break;
                }
            }
        }

        // Compile result
        $result = [
            'cri_i_panel_panel_item_id' => $criIId,
            'cri_i_interpretation' => $cri_i_interpretation,
            'cri_ii_panel_panel_item_id' => $criIIId,
            'cri_ii_interpretation' => $cri_ii_interpretation,
            'aip_panel_panel_item_id' => $aipId,
            'aip_interpretation' => $aip_interpretation,
        ];

        return $result;
    }

    /**
     * AC Formula:
     * (total cholesterol - HDL cholesterol) / HDL cholesterol
     * Total Cholesterol
     * panel_id: 25
     * panel_item_id = 28
     *
     * HDL Cholesterol
     * panel_id: 25
     * panel_item_id = 30
     */
    public function calculateAC($totalCholesterol = null, $hdlCholesterol = null)
    {
        // 1. Get panel_panel_item_id first (always needed)
        $acPanelPanelItemId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->join('master_panel_items', 'master_panel_items.id', '=', 'panel_items.master_panel_item_id')
            ->where('master_panel_items.name', 'Atherogenic Coefficient')
            ->where('panel_items.lab_id', 2) // lab: Innoquest
            ->where('panel_items.code', 'AC')
            ->where('panel_panel_items.panel_id', 25) // panel: Lipid Profile
            ->value('panel_panel_items.id');

        // 2. Cast first, then validate — PHP 8 no longer coerces "" to 0 in loose comparison
        $totalCholesterol = is_null($totalCholesterol) ? null : (float) $totalCholesterol;
        $hdlCholesterol = is_null($hdlCholesterol) ? null : (float) $hdlCholesterol;

        if (is_null($hdlCholesterol) || is_null($totalCholesterol) || $hdlCholesterol == 0 || $totalCholesterol == 0) {
            return [
                'panel_panel_item_id' => $acPanelPanelItemId,
                'value' => null,
                'ac_interpretation' => null,
            ];
        }

        // 3. Calculate AC
        $total = round((($totalCholesterol - $hdlCholesterol) / $hdlCholesterol), 2);
        $ac_interpretation = null;

        // 4. Get interpretation
        $acInterpretations = PanelInterpretation::where('panel_panel_item_id', $acPanelPanelItemId)->get();

        foreach ($acInterpretations as $acInt) {
            $range = trim($acInt->range);

            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($total >= $value) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($total <= $value) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($total < $value) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($total > $value) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($total >= $min && $total <= $max) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($total >= $min && $total <= $max) {
                    $ac_interpretation = $acInt->id;
                    break;
                }
            }
        }

        // 5. Compile result
        $result = [
            'panel_panel_item_id' => $acPanelPanelItemId,
            'value' => $total,
            'ac_interpretation' => $ac_interpretation,
        ];

        return $result;
    }

    public function calculateFIB($age = null, $ast = null, $alt = null, $plateletCount = null)
    {
        // FIB-4 Formula:
        // (Age x AST) / (Platelet Count x √ALT)

        // 1. Get panel_panel_item_id first (always needed)
        $fibPanelPanelItemId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->join('master_panel_items', 'master_panel_items.id', '=', 'panel_items.master_panel_item_id')
            ->where('master_panel_items.name', 'Fibrosis-4')
            ->where('panel_items.lab_id', 2) // lab: Innoquest
            ->where('panel_items.code', 'FIB-4')
            ->where('panel_panel_items.panel_id', 24) // panel: Liver Function Test
            ->value('panel_panel_items.id');

        // 2. Cast first, then validate — PHP 8 no longer coerces "" to 0 in loose comparison
        $age = is_null($age) ? null : (float) $age;
        $ast = is_null($ast) ? null : (float) $ast;
        $alt = is_null($alt) ? null : (float) $alt;
        $plateletCount = is_null($plateletCount) ? null : (float) $plateletCount;

        if (is_null($age) || is_null($ast) || is_null($alt) || is_null($plateletCount) ||
            $age == 0 || $ast == 0 || $alt == 0 || $plateletCount == 0) {
            return [
                'panel_panel_item_id' => $fibPanelPanelItemId,
                'value' => null,
                'fib_interpretation' => null,
            ];
        }

        $fibValue = round((($age * $ast) / ($plateletCount * sqrt($alt))), 2);
        $fibInterpretation = null;

        // 4. Get interpretation
        $fibInterpretations = PanelInterpretation::where('panel_panel_item_id', $fibPanelPanelItemId)->get();

        foreach ($fibInterpretations as $fibInt) {
            $range = trim($fibInt->range);

            // Case 1: ">=" (must check before ">")
            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($fibValue >= $value) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            }
            // Case 2: "<=" (must check before "<")
            elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($fibValue <= $value) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            }
            // Case 3: "<"
            elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($fibValue < $value) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            }
            // Case 4: ">"
            elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($fibValue > $value) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            // Case 5: "1.30 to 2.67" or "1.30 - 2.67" (range)
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($fibValue >= $min && $fibValue <= $max) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($fibValue >= $min && $fibValue <= $max) {
                    $fibInterpretation = $fibInt->id;
                    break;
                }
            }
        }

        // 5. Compile result
        $result = [
            'panel_panel_item_id' => $fibPanelPanelItemId,
            'value' => $fibValue,
            'fib_interpretation' => $fibInterpretation,
        ];

        return $result;
    }

    public function calculateAPRI($ast = null, $astRef = null, $plateletCount = null)
    {
        // APRI Formula:
        // (AST / ULN) / Platelet Count (10^9/L) x 100
        // ULN (Upper Limit of Normal) for AST is typically 40 U/L

        // 1. Get panel_panel_item_id first (always needed)
        $apriPanelPanelItemId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->join('master_panel_items', 'master_panel_items.id', '=', 'panel_items.master_panel_item_id')
            ->where('master_panel_items.name', 'AST to Platelet Ratio Index')
            ->where('panel_items.lab_id', 2) // lab: Innoquest
            ->where('panel_items.code', 'APRI')
            ->where('panel_panel_items.panel_id', 24) // panel: Liver Function Test
            ->value('panel_panel_items.id');

        // 2. Cast first, then validate — PHP 8 no longer coerces "" to 0 in loose comparison
        $ast = is_null($ast) ? null : (float) $ast;
        $astRef = is_null($astRef) ? null : (float) $astRef;
        $plateletCount = is_null($plateletCount) ? null : (float) $plateletCount;

        if (is_null($ast) || is_null($astRef) || is_null($plateletCount) ||
            $ast == 0 || $astRef == 0 || $plateletCount == 0) {
            return [
                'panel_panel_item_id' => $apriPanelPanelItemId,
                'value' => null,
                'apri_interpretation' => null,
            ];
        }

        // 3. Calculate APRI
        $apriValue = round((($ast / $astRef) / $plateletCount) * 100, 2);
        $apriInterpretation = null;

        // 4. Get interpretation
        $apriInterpretations = PanelInterpretation::where('panel_panel_item_id', $apriPanelPanelItemId)->get();

        foreach ($apriInterpretations as $apriInt) {
            $range = trim($apriInt->range);

            // Case 1: ">=" (must check before ">")
            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($apriValue >= $value) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            }
            // Case 2: "<=" (must check before "<")
            elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($apriValue <= $value) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            }
            // Case 3: "<"
            elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($apriValue < $value) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            }
            // Case 4: ">"
            elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($apriValue > $value) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            // Case 5: "0.5 to 1.9" or "0.5 - 1.9" (range)
            } elseif (str_contains($range, ' to ')) {
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($apriValue >= $min && $apriValue <= $max) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($apriValue >= $min && $apriValue <= $max) {
                    $apriInterpretation = $apriInt->id;
                    break;
                }
            }
        }

        // 5. Compile result
        $result = [
            'panel_panel_item_id' => $apriPanelPanelItemId,
            'value' => $apriValue,
            'apri_interpretation' => $apriInterpretation,
        ];

        return $result;
    }

    public function calculateNFS($age = null, $bmi = null, $fasting = false, $ast = null, $alt = null, $plateletCount = null, $albumin = null)
    {
        // NFS Formula:
        // -1.675 + 0.037 x age (years) + 0.094 x BMI (kg/m2) + 1.13 x impaired fasting glucose/diabetes (yes=1, no=0) + 0.99 x AST/ALT ratio - 0.013 x platelet (×10^9/L) - 0.66 x albumin (g/dL)

        // 1. Get panel_panel_item_id first (always needed)
        $nfsPanelPanelItemId = PanelPanelItem::join('panel_items', 'panel_items.id', '=', 'panel_panel_items.panel_item_id')
            ->join('master_panel_items', 'master_panel_items.id', '=', 'panel_items.master_panel_item_id')
            ->where('master_panel_items.name', 'Nonalcoholic Fatty Liver Disease Fibrosis Score')
            ->where('panel_items.lab_id', 2) // lab: Innoquest
            ->where('panel_items.code', 'NFS')
            ->where('panel_panel_items.panel_id', 24) // panel: Liver Function Test
            ->value('panel_panel_items.id');

        // 2. Cast first, then validate — PHP 8 no longer coerces "" to 0 in loose comparison
        $age = is_null($age) ? null : (float) $age;
        $bmi = is_null($bmi) ? null : (float) $bmi;
        $ast = is_null($ast) ? null : (float) $ast;
        $alt = is_null($alt) ? null : (float) $alt;
        $plateletCount = is_null($plateletCount) ? null : (float) $plateletCount;
        $albumin = is_null($albumin) ? null : (float) $albumin;

        // fasting is boolean, not required to be non-null
        if (is_null($age) || is_null($bmi) || is_null($ast) || is_null($alt) || is_null($plateletCount) || is_null($albumin) ||
            $age == 0 || $bmi == 0 || $ast == 0 || $alt == 0 || $plateletCount == 0 || $albumin == 0) {
            return [
                'panel_panel_item_id' => $nfsPanelPanelItemId,
                'value' => null,
                'nfs_interpretation' => null,
            ];
        }

        // 3. Calculate NFS
        $impairedFasting = $fasting ? 1 : 0;
        $astAltRatio = $ast / $alt;

        $nfsValue = round(
            -1.675 +
            (0.037 * $age) +
            (0.094 * $bmi) +
            (1.13 * $impairedFasting) +
            (0.99 * $astAltRatio) -
            (0.013 * $plateletCount) -
            (0.66 * $albumin),
            2
        );
        $nfsInterpretation = null;

        // 4. Get interpretation
        $nfsInterpretations = PanelInterpretation::where('panel_panel_item_id', $nfsPanelPanelItemId)->get(); 

        foreach ($nfsInterpretations as $nfsInt) {
            $range = trim($nfsInt->range);

            if (str_starts_with($range, '>=')) {
                $value = (float) trim(str_replace('>=', '', $range));
                if ($nfsValue >= $value) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<=')) {
                $value = (float) trim(str_replace('<=', '', $range));
                if ($nfsValue <= $value) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '<')) {
                $value = (float) trim(str_replace('<', '', $range));
                if ($nfsValue < $value) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            } elseif (str_starts_with($range, '>')) {
                $value = (float) trim(str_replace('>', '', $range));
                if ($nfsValue > $value) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            } elseif (str_contains($range, ' to ')) {
                // Handle "min to max" format (e.g., "-1.455 to 0.675")
                [$min, $max] = array_map('floatval', explode(' to ', $range));
                if ($nfsValue >= $min && $nfsValue <= $max) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            } elseif (str_contains($range, ' - ')) {
                // Handle "min - max" format with spaces (e.g., "1.30 - 2.67")
                [$min, $max] = array_map('floatval', explode(' - ', $range));
                if ($nfsValue >= $min && $nfsValue <= $max) {
                    $nfsInterpretation = $nfsInt->id;
                    break;
                }
            }
        }


        // 5. Compile result
        $result = [
            'panel_panel_item_id' => $nfsPanelPanelItemId,
            'value' => $nfsValue,
            'nfs_interpretation' => $nfsInterpretation,
        ];

        return $result;
    }
}
