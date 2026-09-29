<?php

namespace App\Services;

/** Exact legacy fixture labels only; audit records remain unchanged in storage. */
class SeededDisplayContent
{
    public const USERS = [
        'admin@assetkeeper.test' => ['Demo Administrator', 'Alex Morgan'],
        'technician@assetkeeper.test' => ['Demo Technician', 'Jordan Lee'],
        'department@assetkeeper.test' => ['Demo Department Staff', 'Taylor Reed'],
        'management@assetkeeper.test' => ['Demo HOD', 'Casey Brooks'],
    ];

    public static function display(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $labels = [
            'Demo asset created' => 'Asset created',
            'Development administrator configured' => 'Administrator account configured',
            'Demo Equipment Supplier' => 'Northbridge ICT Supplies',
            'Demo Academic Officer' => 'Morgan Ellis',
            'Fictional network port fault' => 'Network port fault',
        ];
        foreach (self::USERS as [$old, $new]) {
            $labels[$old] = $new;
        }
        for ($i = 1; $i <= 8; $i++) {
            $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $labels['AK-DEMO-'.$suffix] = 'UPTM-ICT-'.$suffix;
            $labels['FICTIONAL-'.$i] = 'ICT-2026-'.$suffix;
        }

        return $labels[$value] ?? $value;
    }
}
