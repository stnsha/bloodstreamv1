<?php

namespace App\Constants\Innoquest;

class PanelPanelItem
{
    /**
     * In test_result_items table
     *
     * CRI-I
     * panel_panel_item_id = 32
     *
     * CRI-II
     * panel_panel_item_id = 34
     *
     * AIP
     * panel_panel_item_id = 33
     *
     * Total Cholestrol
     * panel_panel_item_id = 28
     *
     * HDL
     * panel_panel_item_id = 30
     *
     * AST
     * panel_panel_item_id = 8
     *
     * Platelets
     * panel_panel_item_id = 61/166
     *
     * ALT
     * panel_panel_item_id = 9
     *
     * Albumin
     * panel_panel_item_id = 2
     * 
     * Glucose Fasting Type
     * panel_panel_item_id = 53
     */
    public const CRI_I = 32;

    public const CRI_II = 34;

    public const AIP = 33;

    public const TOTAL_CHOLESTEROL = 28;

    public const HDL = 30;

    public const AST = 8;

    public const PLATELETS = 61;

    public const PLATELETS_ALT = 166;

    public const ALT = 9;

    public const ALBUMIN = 2;

    public const GLUCOSE_FASTING_TYPE = 53;

    public const PANEL_PANEL_ITEM_IDS = [
        self::CRI_I,
        self::CRI_II,
        self::AIP,
        self::TOTAL_CHOLESTEROL,
        self::HDL,
        self::AST,
        self::PLATELETS,
        self::PLATELETS_ALT,
        self::ALT,
        self::ALBUMIN,
        self::GLUCOSE_FASTING_TYPE,
    ];
}
