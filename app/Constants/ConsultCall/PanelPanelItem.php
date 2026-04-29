<?php

namespace App\Constants\ConsultCall;

class PanelPanelItem
{
    /**
     * Total Cholestrol
     * panel_panel_item_id = 28, 118
     *
     * LDL-C
     * panel_panel_item_id = 31, 121
     *
     * eGFR
     * panel_panel_item_id = 42, 218
     *
     * Glycated Hemoglobin
     * panel_panel_item_id = 49, 293, 663
     * unit = %
     *
     * HBA1C
     * panel_panel_item_id = 50, 294, 664
     * unit = mmol/mol
     *
     * ALT
     * panel_panel_item_id = 9
     */
    public const tc = [28, 118];

    public const ldlc = [31, 121];

    public const egfr = [42, 218];

    public const hba1c_percent = [49, 293, 663];

    public const hba1c = [50, 294, 664];

    public const alt = [9];

    /**
     * Required categories for filtering - maps category name to its IDs
     */
    public const REQUIRED_CATEGORIES = [
        'tc' => self::tc,
        'ldlc' => self::ldlc,
        'egfr' => self::egfr,
        'hba1c_percent' => self::hba1c_percent,
        'hba1c' => self::hba1c,
        'alt' => self::alt,
    ];

    /**
     * All panel_panel_item IDs combined
     */
    public const ALL_IDS = [28, 118, 31, 121, 42, 218, 49, 293, 663, 50, 294, 664, 9];
}
