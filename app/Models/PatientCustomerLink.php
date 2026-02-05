<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCustomerLink extends Model
{
    use HasFactory;

    const LINK_TYPE_EXACT_MATCH = 'exact_match';
    const LINK_TYPE_FUZZY_MATCH = 'fuzzy_match';
    const LINK_TYPE_MANUAL_LINK = 'manual_link';

    protected $fillable = [
        'patient_id',
        'customer_id',
        'link_type',
        'confidence_score',
        'match_candidate_id',
        'linked_by',
        'linked_at',
        'notes',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:4',
        'linked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the patient for this link.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the match candidate that created this link (if any).
     */
    public function matchCandidate(): BelongsTo
    {
        return $this->belongsTo(PatientMatchCandidate::class, 'match_candidate_id');
    }

    /**
     * Get the admin user who created/approved this link.
     */
    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /**
     * Scope for exact match links.
     */
    public function scopeExactMatch($query)
    {
        return $query->where('link_type', self::LINK_TYPE_EXACT_MATCH);
    }

    /**
     * Scope for fuzzy match links.
     */
    public function scopeFuzzyMatch($query)
    {
        return $query->where('link_type', self::LINK_TYPE_FUZZY_MATCH);
    }

    /**
     * Scope for manual links.
     */
    public function scopeManualLink($query)
    {
        return $query->where('link_type', self::LINK_TYPE_MANUAL_LINK);
    }

    /**
     * Check if link is from exact match.
     */
    public function isExactMatch(): bool
    {
        return $this->link_type === self::LINK_TYPE_EXACT_MATCH;
    }

    /**
     * Check if link is from fuzzy match.
     */
    public function isFuzzyMatch(): bool
    {
        return $this->link_type === self::LINK_TYPE_FUZZY_MATCH;
    }

    /**
     * Check if link is manual.
     */
    public function isManualLink(): bool
    {
        return $this->link_type === self::LINK_TYPE_MANUAL_LINK;
    }
}
