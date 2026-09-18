<?php

namespace App\Services;

use App\Models\PanelPanelItem;
use App\Models\PanelPanelProfile;
use App\Models\TestResult;

/**
 * Builds the same Profile > Category > Panel > Panel Item hierarchy used by
 * the Innoquest PDF report (see PDFController::exportByTestResultId()), for
 * callers that need it as plain JSON rather than a rendered PDF.
 *
 * Kept as a standalone copy of that report's hierarchy-building logic rather
 * than an extraction from PDFController, so PDF generation is untouched.
 */
class TestResultHierarchyService
{
    protected const HAE_ORDER = [
        'Haemoglobin',
        'Red Cell Count',
        'Packed Cell Volume',
        'Mean Cell Volume',
        'Mean Cell Haemoglobin',
        'MCHC',
        'Red Cell Distribution Width',
        'White Cell Count',
        'Neutrophils',
        'Lymphocytes',
        'Monocytes',
        'Eosinophils',
        'Basophils',
        'N:L Ratio',
        'Platelets',
        'E.S.R',
        'Blood Film',
    ];

    protected const COMBINABLE_NAMES = ['Neutrophils', 'Lymphocytes', 'Monocytes', 'Eosinophils', 'Basophils'];

    /**
     * @return array{profiles?: array, categories?: array, panels?: array}
     */
    public function build(TestResult $testResult): array
    {
        $profilesData = [];
        $hasProfiles = count($testResult->profiles) > 0;

        if ($hasProfiles) {
            foreach ($testResult->testResultProfiles as $trp) {
                $ppps = PanelPanelProfile::with(['panel', 'panelProfile'])
                    ->where('panel_profile_id', $trp->panel_profile_id)
                    ->get();

                foreach ($ppps as $ppp) {
                    $profilesData['profiles'][$ppp->panel_profile_id]['profile_id'] = $ppp->panelProfile->id;
                    $profilesData['profiles'][$ppp->panel_profile_id]['profile_name'] = $ppp->panelProfile->name;
                    $profilesData['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_name'] = $ppp->panel->name;
                    $profilesData['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_profile_sequence'] = $ppp->sequence;
                }
            }
        }

        $hierarchicalData = [];
        $combinedItems = [];

        foreach ($testResult->testResultItems as $ri) {
            $refRangeId = $ri->reference_range_id;

            $ppi = PanelPanelItem::with([
                'panel',
                'panel.panelCategory',
                'panelItem',
                'referenceRanges' => function ($query) use ($refRangeId) {
                    $query->where('id', $refRangeId);
                },
            ])->find($ri->panel_panel_item_id);

            if (! $ppi || ! $ppi->panel || ! $ppi->panelItem) {
                continue;
            }

            $itemComments = [];
            foreach ($ri->panelComments as $panelComment) {
                if ($panelComment->masterPanelComment) {
                    $itemComments[] = ['comment' => $panelComment->masterPanelComment->comment];
                }
            }

            $referenceRange = $ppi->referenceRanges->first()->value ?? null;
            if ($referenceRange) {
                $referenceRange = str_replace(['(', ')'], '', $referenceRange);
            }

            $unit = $ppi->panelItem->unit;
            if (! empty($unit)) {
                $unit = preg_replace('/\*(\d+)/', '<sup>$1</sup>', $unit);
                $unit = preg_replace('/([a-zA-Z])(\d+)/', '$1<sup>$2</sup>', $unit);
            }

            $panelItemData = [
                'panel_item_id' => $ppi->panel_item_id,
                'panel_item_name' => $ppi->panelItem->name,
                'chinese_character' => $ppi->panelItem->chi_character,
                'panel_item_unit' => $unit,
                'result_value' => $ri->value,
                'result_flag' => $ri->flag != 'N' ? '*' : '',
                'is_tagon' => $ri->is_tagon,
                'result_sequence' => $ri->sequence,
                'reference_range' => $referenceRange !== null ? '(' . $referenceRange . ')' : '',
                'is_percentage' => false,
                'percentage_value' => null,
                'item_comments' => $itemComments,
            ];

            $itemName = $ppi->panelItem->name;
            $itemUnit = $ppi->panelItem->unit;
            $isPercentage = $itemUnit === '%';
            $isAbsolute = $itemUnit === 'x 10*9/L';
            $cleanItemName = str_replace(' %', '', $itemName);
            $isCombinable = in_array($cleanItemName, self::COMBINABLE_NAMES);

            if ($isCombinable) {
                $baseKey = $cleanItemName . '_' . $ppi->panel_id;

                if ($isPercentage) {
                    $combinedItems[$baseKey]['percentage'] = $panelItemData;
                } elseif ($isAbsolute) {
                    $combinedItems[$baseKey]['absolute'] = $panelItemData;
                }
            } else {
                $panelItemData['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel,
                ];
                $hierarchicalData['_temp_items'][] = $panelItemData;
            }
        }

        foreach ($combinedItems as $items) {
            if (! isset($items['absolute'])) {
                continue;
            }

            $finalItem = $items['absolute'];

            if (isset($items['percentage'])) {
                $finalItem['is_percentage'] = true;
                $finalItem['percentage_value'] = $items['percentage']['result_value'];
                $finalItem['item_comments'] = array_merge(
                    $finalItem['item_comments'] ?? [],
                    $items['percentage']['item_comments'] ?? []
                );
            }

            $ppi = PanelPanelItem::with(['panel', 'panel.panelCategory'])
                ->where('panel_item_id', $finalItem['panel_item_id'])
                ->first();

            if (! $ppi) {
                continue;
            }

            $finalItem['_hierarchy_info'] = [
                'panel_id' => $ppi->panel_id,
                'panel' => $ppi->panel,
            ];
            $hierarchicalData['_temp_items'][] = $finalItem;
        }

        $processedItems = $hierarchicalData['_temp_items'] ?? [];
        $hierarchicalData = [];

        foreach ($processedItems as $panelItemData) {
            $ppi = (object) $panelItemData['_hierarchy_info'];
            unset($panelItemData['_hierarchy_info']);

            $profileId = null;
            $profileName = null;
            $profileSequence = null;

            if ($hasProfiles && isset($profilesData['profiles'])) {
                foreach ($profilesData['profiles'] as $profileData) {
                    if (isset($profileData['panels'][$ppi->panel_id])) {
                        $profileId = $profileData['profile_id'];
                        $profileName = $profileData['profile_name'];
                        $profileSequence = $profileData['panels'][$ppi->panel_id]['panel_profile_sequence'];

                        break;
                    }
                }
            }

            if ($hasProfiles && $profileId) {
                $categoryId = $ppi->panel->panel_category_id ?? 'no_category';
                $categoryName = $ppi->panel->panelCategory->name ?? 'No Category';

                $hierarchicalData['profiles'][$profileId]['profile_id'] ??= $profileId;
                $hierarchicalData['profiles'][$profileId]['profile_name'] ??= $profileName;
                $hierarchicalData['profiles'][$profileId]['profile_sequence'] ??= $profileSequence;

                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['category_id'] ??= $categoryId;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['category_name'] ??= $categoryName;

                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_id'] ??= $ppi->panel_id;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_name'] ??= $ppi->panel->name;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_sequence'] ??= $ppi->panel->sequence;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_profile_sequence'] ??= $profileSequence;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_category_id'] ??= $ppi->panel->panel_category_id;
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_comments'] ??= [];
                $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            } elseif ($ppi->panel->panel_category_id) {
                $categoryId = $ppi->panel->panel_category_id;
                $categoryName = $ppi->panel->panelCategory->name;

                $hierarchicalData['categories'][$categoryId]['category_id'] ??= $categoryId;
                $hierarchicalData['categories'][$categoryId]['category_name'] ??= $categoryName;

                $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_id'] ??= $ppi->panel_id;
                $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_name'] ??= $ppi->panel->name;
                $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_sequence'] ??= $ppi->panel->sequence;
                $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_comments'] ??= [];
                $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            } else {
                $hierarchicalData['panels'][$ppi->panel_id]['panel_id'] ??= $ppi->panel_id;
                $hierarchicalData['panels'][$ppi->panel_id]['panel_name'] ??= $ppi->panel->name;
                $hierarchicalData['panels'][$ppi->panel_id]['panel_sequence'] ??= $ppi->panel->sequence;
                $hierarchicalData['panels'][$ppi->panel_id]['panel_category_id'] ??= $ppi->panel->panel_category_id;
                $hierarchicalData['panels'][$ppi->panel_id]['panel_comments'] ??= [];
                $hierarchicalData['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            }
        }

        $this->buildPanelCommentsFromItems($hierarchicalData);

        if (isset($hierarchicalData['profiles'])) {
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (! isset($profile['categories'])) {
                    continue;
                }

                uasort($profile['categories'], fn ($a, $b) => $this->averageSequence($a['panels']) <=> $this->averageSequence($b['panels']));

                foreach ($profile['categories'] as &$category) {
                    uasort($category['panels'], fn ($a, $b) => ($a['panel_profile_sequence'] ?? 999999) <=> ($b['panel_profile_sequence'] ?? 999999));

                    foreach ($category['panels'] as &$panel) {
                        $panel['panel_items'] = $this->sortPanelItems($panel['panel_items'], $category['category_id']);
                    }
                    unset($panel);
                }
                unset($category);
            }
            unset($profile);
        }

        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (! isset($category['panels'])) {
                    continue;
                }

                foreach ($category['panels'] as &$panel) {
                    $panel['panel_items'] = $this->sortPanelItems($panel['panel_items'], $category['category_id']);
                }
                unset($panel);
            }
            unset($category);
        }

        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                $panel['panel_items'] = $this->sortPanelItems($panel['panel_items'], $panel['panel_category_id']);
            }
            unset($panel);
        }

        if (! $hasProfiles) {
            unset($hierarchicalData['profiles']);
        }

        if (isset($hierarchicalData['profiles'])) {
            $hierarchicalData['profiles'] = array_values($hierarchicalData['profiles']);
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    $profile['categories'] = array_values($profile['categories']);
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            $category['panels'] = array_values($category['panels']);
                        }
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        if (isset($hierarchicalData['categories'])) {
            $hierarchicalData['categories'] = array_values($hierarchicalData['categories']);
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    $category['panels'] = array_values($category['panels']);
                }
            }
            unset($category);
        }

        if (isset($hierarchicalData['panels'])) {
            $hierarchicalData['panels'] = array_values($hierarchicalData['panels']);
        }

        return $hierarchicalData;
    }

    protected function averageSequence(array $panels): float
    {
        $total = 0;
        $count = 0;

        foreach ($panels as $panel) {
            if (isset($panel['panel_profile_sequence'])) {
                $total += $panel['panel_profile_sequence'];
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 999999;
    }

    protected function sortPanelItems(array $panelItems, $categoryId): array
    {
        if ($categoryId == 4) {
            $itemsByName = [];
            foreach ($panelItems as $item) {
                $itemsByName[$item['panel_item_name']] = $item;
            }

            $orderedItems = [];
            foreach (self::HAE_ORDER as $expectedName) {
                if (isset($itemsByName[$expectedName])) {
                    $orderedItems[] = $itemsByName[$expectedName];
                }
            }

            foreach ($panelItems as $item) {
                if (! in_array($item['panel_item_name'], self::HAE_ORDER)) {
                    $orderedItems[] = $item;
                }
            }

            return $orderedItems;
        }

        usort($panelItems, fn ($a, $b) => $a['result_sequence'] - $b['result_sequence']);

        return $panelItems;
    }

    protected function buildPanelCommentsFromItems(array &$hierarchicalData): void
    {
        if (isset($hierarchicalData['profiles'])) {
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            foreach ($category['panels'] as &$panel) {
                                $this->collectPanelComments($panel);
                            }
                        }
                    }
                }
            }
        }

        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    foreach ($category['panels'] as &$panel) {
                        $this->collectPanelComments($panel);
                    }
                }
            }
        }

        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                $this->collectPanelComments($panel);
            }
        }
    }

    protected function collectPanelComments(array &$panel): void
    {
        $uniqueComments = [];
        $seenComments = [];

        if (isset($panel['panel_items'])) {
            foreach ($panel['panel_items'] as $item) {
                if (! empty($item['item_comments'])) {
                    foreach ($item['item_comments'] as $comment) {
                        $commentText = $comment['comment'];

                        if (! in_array($commentText, $seenComments)) {
                            $uniqueComments[] = $comment;
                            $seenComments[] = $commentText;
                        }
                    }
                }
            }
        }

        $panel['panel_comments'] = $uniqueComments;
    }
}
