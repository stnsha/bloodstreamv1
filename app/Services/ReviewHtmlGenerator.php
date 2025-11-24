<?php

namespace App\Services;

class ReviewHtmlGenerator
{
    /**
     * Convert JSON AI response to HTML table blocks
     * Extracted from DoctorReviewController and BloodTestController
     * to eliminate 109 lines of code duplication
     */
    public function convertToHtml(array $data): string
    {
        $html = '';

        // SECTION A1: Health at a Glance
        if (!empty($data['section_a1'])) {
            $html .= $this->renderSectionA1($data['section_a1']);
        }

        // SECTION A2: Body System Highlights
        if (!empty($data['section_a2'])) {
            $html .= $this->renderSectionA2($data['section_a2']);
        }

        // SECTION B: 3-6 Month Health Action
        if (!empty($data['section_b'])) {
            $html .= $this->renderSectionB($data['section_b']);
        }

        // SECTION C: With Care from Alpro
        if (!empty($data['section_c'])) {
            $html .= $this->renderSectionC($data['section_c']);
        }

        return $html;
    }

    /**
     * Render Section A1: Your Health at a Glance
     */
    protected function renderSectionA1(array $rows): string
    {
        $html = '<div class="review-section">';
        $html .= '<h5>Your Health at a Glance</h5>';
        $html .= '<table class="review-table"><thead><tr>
                <th>Health Area</th>
                <th>Status</th>
                <th>Notes</th>
              </tr></thead><tbody>';

        foreach ($rows as $row) {
            $statusIcon = match (strtolower($row['status'])) {
                'normal', 'optimal' => '🟢',
                'borderline' => '🟡',
                'need attention', 'needs attention' => '🔴',
                default => '⚪️',
            };

            $html .= '<tr>';
            $html .= '<td>' . e($row['health_area']) . '</td>';
            $html .= '<td>' . $statusIcon . ' ' . e(ucwords($row['status'])) . '</td>';
            $html .= '<td>' . e($row['notes']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Render Section A2: Your Body System Highlights
     */
    protected function renderSectionA2(array $highlights): string
    {
        $html = '<div class="review-section">';
        $html .= '<h5>Your Body System Highlights</h5>';
        $html .= '<ol class="review-list">';
        foreach ($highlights as $highlight) {
            $html .= '<li class="highlight">' . $highlight . '</li>';
        }
        $html .= '</ol></div>';
        return $html;
    }

    /**
     * Render Section B: 3-6 Month Health Action
     */
    protected function renderSectionB(array $rows): string
    {
        $html = '<div class="review-section">';
        $html .= '<h5>3-6 Month Health Action</h5>';
        $html .= '<table class="review-table"><thead><tr>
            <th>Timeline</th>
            <th>Action</th>
            <th>Goals</th>
            <th>Alpro Care for You</th>
            <th>Appointment Date & Place</th>
          </tr></thead><tbody>';

        foreach ($rows as $row) {
            // Convert 'action' into list if contains ';'
            $action = $this->formatListField($row['action'] ?? '-');

            // Convert 'goals' into list if contains ';'
            $goals = $this->formatListField($row['goals'] ?? '-');

            $html .= '<tr>';
            $html .= '<td>' . e($row['timeline'] ?? '-') . '</td>';
            $html .= '<td>' . $action . '</td>';
            $html .= '<td>' . $goals . '</td>';
            $html .= '<td>' . e($row['care'] ?? '-') . '</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Render Section C: With Care, from Alpro
     */
    protected function renderSectionC(string $text): string
    {
        $html = '<div class="review-section">';
        $html .= '<h5>With Care, from Alpro</h5>';
        $html .= '<p>' . nl2br(e($text)) . '</p>';
        $html .= '<p class="disclaimer">
                Disclaimer: This report is for educational purposes only and should not replace consultation with a qualified healthcare professional.
              </p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Format field with semicolon-separated items into HTML list
     */
    protected function formatListField(string $field): string
    {
        // Split on ";" or any variation of <br>
        $items = preg_split('/;|<br\s*\/?>/i', $field);

        $items = array_filter(array_map('trim', $items));

        if (count($items) > 1) {
            $html = '<ul>';
            foreach ($items as $item) {
                $html .= '<li>' . e($item) . '</li>';
            }
            $html .= '</ul>';

            return $html;
        }

        return e($field);
    }
}