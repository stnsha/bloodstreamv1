<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PanelItem;
use Exception;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tr = new GoogleTranslate('zh-CN');
        $tr->setSource('en');

        $panelItems = PanelItem::whereNull('chi_character')->get();

        $total = $panelItems->count();
        $success = 0;
        $failed = 0;

        foreach ($panelItems as $pi) {
            try {
                $pi->chi_character = $tr->translate($pi->name);
                $pi->save();
                $success++;
            } catch (Exception $e) {
                $failed++;
            }
        }

        Log::info('Translation Seeder Summary', [
            'total_records' => $total,
            'translated_successfully' => $success,
            'failed_translations' => $failed,
        ]);
    }
}
