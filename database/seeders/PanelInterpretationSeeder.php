<?php

namespace Database\Seeders;

use App\Models\MasterPanelItem;
use App\Models\PanelInterpretation;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use Illuminate\Database\Seeder;

class PanelInterpretationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * CRI-I (Castelli Risk Index I)
         * panel_id: 25
         * panel_item_id: 32
         * panel_panel_item_id: 32
         *
         * CRI-II (Castelli Risk Index II)
         * panel_id: 25
         * panel_item_id: 34
         * panel_panel_item_id: 34
         *
         * AIP (Atherogenic Index of Plasma)
         * panel_id: 25
         * panel_item_id: 33
         * panel_panel_item_id: 33
         *
         * AC (Atherogenic Coefficient)
         * panel_item_id: (need to create this panel first)
         *
         * FIB4 (Fibrosis-4 Index)
         * panel_item_id: (need to create this panel first)
         *
         * APRI (AST to Platelet Ratio Index)
         * panel_item_id: (need to create this panel first)
         *
         * NFS (Nonalcoholic Fatty Liver Disease Fibrosis Score)
         * panel_item_id: (need to create this panel first)
         */

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

        /**
         * FIB4 Formula:
         * (age * ast) / (platelet count * √alt)
         * age (years) : from patients -> age field
         * AST
         * panel_id: 24
         * panel_item_id = 8
         * panel_panel_item_id = 8
         *
         * Platelets
         * panel_id: 16
         * panel_item_id = 57
         * panel_panel_item_id = 61
         *
         * ALT
         * panel_id: 24
         * panel_item_id = 9
         * panel_panel_item_id = 9
         */

        /**
         * APRI Formula:
         * (AST / ULN of AST) / Platelet Count) x 100
         * AST
         * panel_id: 24
         * panel_item_id = 8
         * panel_panel_item_id = 8
         *
         *  * Platelets
         * panel_id: 16
         * panel_item_id = 57
         * panel_panel_item_id = 61
         *
         * ULN of AST = Find in ReferenceRange (different patient, different range)
         */

        /**
         * NFS Formula:
         * -1.675 + (0.037 * age (years)) + (0.094 * BMI) + (1.13 * Fasting (yes = 1, no = 0)) + (0.99 * AST/ALT) - (0.013 * platelet count) - (0.66 * albumin)
         * Albumin
         * panel_id: 24
         * panel_item_id = 2
         * panel_panel_item_id = 2
         */

        // 1. Create AC panel item
        $acMaster = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Atherogenic Coefficient',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 2. Create Panel Item for AC for lab_id: 2 (innoquest)
        $acPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $acMaster->id,
                'name' => 'Atherogenic Coefficient',
                'code' => 'AC',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // Attach to Lipid Panel (panel_id: 25)
        $acPanelItem->panels()->syncWithoutDetaching([25]);
        $acPanelPanelItemId = PanelPanelItem::where('panel_id', 25)->where('panel_item_id', $acPanelItem->id)->first()?->id;

        // 3. Create FIB4 panel item
        $fib4Master = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Fibrosis-4',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 4. Create Panel Item for FIB4 for lab_id: 2 (innoquest)
        $fib4PanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $fib4Master->id,
                'name' => 'Fibrosis-4',
                'code' => 'FIB-4',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // Attach to Liver Panel (panel_id: 24)
        $fib4PanelItem->panels()->syncWithoutDetaching([24]);
        $fib4PanelPanelItemId = PanelPanelItem::where('panel_id', 24)->where('panel_item_id', $fib4PanelItem->id)->first()?->id;

        // 5. Create APRI panel item
        $apriMaster = MasterPanelItem::firstOrCreate(
            [
                'name' => 'AST to Platelet Ratio Index',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 6. Create Panel Item for APRI for lab_id: 2 (innoquest)
        $apriPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $apriMaster->id,
                'name' => 'AST to Platelet Ratio Index',
                'code' => 'APRI',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // 7. Attach to Liver Panel (panel_id: 24)
        $apriPanelItem->panels()->syncWithoutDetaching([24]);
        $apriPanelPanelItemId = PanelPanelItem::where('panel_id', 24)->where('panel_item_id', $apriPanelItem->id)->first()?->id;

        // 8. Create NFS panel item
        $nfsMaster = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Nonalcoholic Fatty Liver Disease Fibrosis Score',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 9. Create Panel Item for NFS for lab_id: 2 (innoquest)
        $nfsPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $nfsMaster->id,
                'name' => 'Nonalcoholic Fatty Liver Disease Fibrosis Score',
                'code' => 'NFS',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // 10. Attach to Liver Panel (panel_id: 24)
        $nfsPanelItem->panels()->syncWithoutDetaching([24]);
        $nfsPanelPanelItemId = PanelPanelItem::where('panel_id', 24)->where('panel_item_id', $nfsPanelItem->id)->first()?->id;

        // 11. Create CRI-I Panel Item
        $criIPanelItem = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Castelli Risk Index I',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 12. Create Panel Item for CRI-I for lab_id: 2 (innoquest)
        $criIPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $criIPanelItem->id,
                'name' => 'Castelli Risk Index I',
                'code' => 'CRI-I',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // Attach to Lipid Panel (panel_id: 25)
        $criIPanelItem->panels()->syncWithoutDetaching([25]);
        $criIPanelPanelItemId = PanelPanelItem::where('panel_id', 25)->where('panel_item_id', $criIPanelItem->id)->first()?->id;

        // 13. Create CRI-II Panel Item
        $criIIPanelItem = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Castelli Risk Index II',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 14. Create Panel Item for CRI-II for lab_id: 2 (innoquest)
        $criIIPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $criIIPanelItem->id,
                'name' => 'Castelli Risk Index II',
                'code' => 'CRI-II',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // Attach to Lipid Panel (panel_id: 25)
        $criIIPanelItem->panels()->syncWithoutDetaching([25]);
        $criIIPanelPanelItemId = PanelPanelItem::where('panel_id', 25)->where('panel_item_id', $criIIPanelItem->id)->first()?->id;

        // 15. Create AIP Panel Item
        $aipPanelItem = MasterPanelItem::firstOrCreate(
            [
                'name' => 'Atherogenic Index of Plasma',
                'chi_character' => null,
                'unit' => null,
            ]
        );

        // 16. Create Panel Item for AIP for lab_id: 2 (innoquest)
        $aipPanelItem = PanelItem::firstOrCreate(
            [
                'lab_id' => 2,
                'master_panel_item_id' => $aipPanelItem->id,
                'name' => 'Atherogenic Index of Plasma',
                'code' => 'AIP',
                'unit' => null,
                'identifier' => null,
            ]
        );

        // Attach to Lipid Panel (panel_id: 25)
        $aipPanelItem->panels()->syncWithoutDetaching([25]);
        $aipPanelPanelItemId = PanelPanelItem::where('panel_id', 25)->where('panel_item_id', $aipPanelItem->id)->first()?->id;

        /********************************************************************************/

        // 1. Create interpretations for CRI-I
        $interpretations = [
            [
                'range' => '< 3.5',
                'interpretation' => 'Ideal',
            ],
            [
                'range' => '3.5 - 5.0',
                'interpretation' => 'Intermediate Risk',
            ],
            [
                'range' => '> 5.0',
                'interpretation' => 'Higher Risk',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $criIPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 2. Create interpretations for CRI-II
        $interpretations = [
            [
                'range' => '< 2.0',
                'interpretation' => 'Ideal',
            ],
            [
                'range' => '2.0 - 3.0',
                'interpretation' => 'Intermediate Risk',
            ],
            [
                'range' => '> 3.0',
                'interpretation' => 'High Risk',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $criIIPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 3. Create interpretations for AIP
        $interpretations = [
            [
                'range' => '< 0.11',
                'interpretation' => 'Low risk',
            ],
            [
                'range' => '0.11 - 0.21',
                'interpretation' => 'Intermediate Risk',
            ],
            [
                'range' => '> 0.21',
                'interpretation' => 'High Risk',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $aipPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 4. Create interpretations for AC
        $interpretations = [
            [
                'range' => '<= 3.0',
                'interpretation' => 'Lower Cardiovascular Risk',
            ],
            [
                'range' => '> 3.0',
                'interpretation' => 'Increased Risk',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $acPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 5. Create interpretations for FIB4
        $interpretations = [
            [
                'range' => '< 1.30',
                'interpretation' => 'Low Risk of Advanced Fibrosis',
            ],
            [
                'range' => '1.30 - 2.67',
                'interpretation' => 'Ideterminate',
            ],
            [
                'range' => '> 2.67',
                'interpretation' => 'High Risk of Advanced Fibrosis',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $fib4PanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 6. Create interpretations for APRI
        $interpretations = [
            [
                'range' => '< 0.5',
                'interpretation' => 'Low Likelihood of Significant Fibrosis',
            ],
            [
                'range' => '0.5 - 1.9',
                'interpretation' => 'Suggests Significant Risk',
            ],
            [
                'range' => '>= 2.0',
                'interpretation' => 'Suggests cirrhosis',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $apriPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }

        // 7. Create interpretations for NFS
        $interpretations = [
            [
                'range' => '< -1.455',
                'interpretation' => 'Low risk of advanced fibrosis',
            ],
            [
                'range' => '-1.455 to 0.675',
                'interpretation' => 'Indeterminate',
            ],
            [
                'range' => '> 0.675',
                'interpretation' => 'High risk of advanced fibrosis',
            ],
        ];

        foreach ($interpretations as $item) {
            PanelInterpretation::updateOrCreate(
                [
                    'panel_panel_item_id' => $nfsPanelPanelItemId,
                    'range' => $item['range'],
                ],
                [
                    'interpretation' => $item['interpretation'],
                ]
            );
        }
    }
}
