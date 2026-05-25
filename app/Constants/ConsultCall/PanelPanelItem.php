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
     * 
     * Full Blood Examination (panel_id = 16)
     * 
     * Haemoglobin
     * panel_panel_item_id = 54
     * 
     * Red Cell Count
     * panel_panel_item_id = 55
     * 
     * Packed Cell Volume
     * panel_panel_item_id = 56
     * 
     * Mean Cell Volume
     * panel_panel_item_id = 57
     * 
     * Mean Cell Haemoglobin
     * panel_panel_item_id = 58
     * 
     * Mean Corpuscular Hemoglobin Concentration (MCHC)
     * panel_panel_item_id = 59
     * 
     * Red Cell Distribution Width (RDW)
     * panel_panel_item_id = 60
     * 
     * Iron Studies (panel_id = 79) - Special tests
     * Serum Iron
     * panel_panel_item_id = 27, 262
     * 
     * Ferritin
     * panel_panel_item_id = 266
     */
    public const tc = [28, 118];

    public const ldlc = [31, 121];

    public const egfr = [42, 218];

    public const hba1c_percent = [49, 293, 663];

    public const hba1c = [50, 294, 664];

    public const alt = [9];

    public const hae = [54];

    public const rcc = [55];

    public const pcv = [56];

    public const mcv = [57];

    public const mch = [58];

    public const mchc = [59];

    public const rdw = [60];

    public const s_iron = [27, 262];

    public const ferritin = [266];

    /**
     * Date from which anemia conditions (31-75) become active.
     */
    public const ANEMIA_ACTIVE_DATE = '2026-06-02';

    /**
     * Gate categories — all must be present in a test result for eligibility to proceed.
     * Anemia categories are excluded; their evaluators handle null values internally.
     */
    public const BASE_REQUIRED_CATEGORIES = [
        'tc'            => self::tc,
        'ldlc'          => self::ldlc,
        'egfr'          => self::egfr,
        'hba1c_percent' => self::hba1c_percent,
        'hba1c'         => self::hba1c,
        'alt'           => self::alt,
    ];

    /**
     * Full category map — used for panel value lookups across all categories.
     */
    public const REQUIRED_CATEGORIES = [
        'tc'            => self::tc,
        'ldlc'          => self::ldlc,
        'egfr'          => self::egfr,
        'hba1c_percent' => self::hba1c_percent,
        'hba1c'         => self::hba1c,
        'alt'           => self::alt,
        'hae'           => self::hae,
        'rcc'           => self::rcc,
        'pcv'           => self::pcv,
        'mcv'           => self::mcv,
        'mch'           => self::mch,
        'mchc'          => self::mchc,
        'rdw'           => self::rdw,
        's_iron'        => self::s_iron,
        'ferritin'      => self::ferritin,
    ];

    /**
     * All panel_panel_item IDs combined
     */
    public const ALL_IDS = [28, 118, 31, 121, 42, 218, 49, 293, 663, 50, 294, 664, 9, 54, 55, 56, 57, 58, 59, 60, 27, 262, 266];
}
