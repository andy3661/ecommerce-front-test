<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    /**
     * Get the user that owns the address
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the formatted address
     */
    public function getFormattedAddressAttribute(): string
    {
        $address = $this->address_line_1;
        
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        $address .= ', ' . $this->country;
        
        return $address;
    }

    /**
     * Get the address type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'home' => 'Home',
            'work' => 'Work',
            'other' => 'Other',
            default => ucfirst($this->type)
        };
    }

    /**
     * Scope to get default address
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get addresses by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}