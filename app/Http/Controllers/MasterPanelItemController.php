<?php

namespace App\Http\Controllers;

use App\Models\MasterPanelItem;
use Illuminate\Http\Request;
use Stichoza\GoogleTranslate\GoogleTranslate;

class MasterPanelItemController extends Controller
{
    /**
     * Translate PanelItem to chinese character
     */
    public function translate()
    {
        $masterPanelItems = MasterPanelItem::all();

        $tr = new GoogleTranslate();
        $tr->setSource('en');
        $tr->setTarget('zh-CN');

        foreach($masterPanelItems as $pi) {
            if (is_null($pi->chi_character)) {
                $pi->chi_character = $tr->translate($pi->name);
                $pi->save();
            }
        }
    }
}