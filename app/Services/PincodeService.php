<?php

namespace App\Services;

use App\Models\ServiceablePincode;

class PincodeService
{
    /**
     * Check if a pincode is serviceable for delivery
     */
    public function isServiceable(string $pincode): bool
    {
        return ServiceablePincode::query()
            ->where('pincode', $pincode)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all serviceable pincodes
     */
    public function getServiceablePincodes()
    {
        return ServiceablePincode::where('is_active', true)
            ->orderBy('pincode')
            ->get();
    }

    /**
     * Add a new serviceable pincode
     */
    public function addPincode(string $pincode, ?string $label = null): ServiceablePincode
    {
        return ServiceablePincode::create([
            'pincode' => $pincode,
            'label' => $label,
            'is_active' => true,
        ]);
    }

    /**
     * Deactivate a serviceable pincode
     */
    public function deactivatePincode(string $pincode): bool
    {
        return ServiceablePincode::where('pincode', $pincode)
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Validate user's pincode for subscription
     */
    public function validateUserPincode(string $userPincode): array
    {
        if (!$this->isServiceable($userPincode)) {
            return [
                'is_valid' => false,
                'message' => "Delivery is not available in pincode {$userPincode}",
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'Pincode is serviceable',
        ];
    }
}
