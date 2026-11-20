#!/usr/bin/env python3
"""
Applies the M3 panel preference wiring to PRODUCTION's copy of
app/Http/Controllers/Panel/UserController.php.

This file cannot be copied from staging. Production's copy is NEWER: another
developer moved the regulatory form lookup from RegulatoryFormTemplate to the
admin Forms builder, and staging still holds the older version. Copying staging
over it would revert 163 lines of somebody else's work.

Our contribution to that file is two edits:

  1. The unit validation rule builds its allow-list from the configured
     conversions, which includes units deactivated under D-08. It now builds it
     from getAvailableUnits(), so an inactive unit is rejected.

  2. The update loop wrote preferred_{type}_unit straight onto the users table.
     It now writes through PreferenceRepository into user_unit_preferences,
     leaving the legacy columns untouched as read-only fallback.

Edit 2 is applied without introducing a local variable, because production
already uses $unitPreferences earlier in this class for a different purpose.

Run with --dry-run to verify the anchors without writing anything.
"""

import sys

PATH = 'app/Http/Controllers/Panel/UserController.php'

RULE_OLD = "implode(',', array_keys(config(\"units.conversions.{$type}\", [])))"
RULE_NEW = "implode(',', array_keys($unitService->getAvailableUnits($type)))"

WRITE_OLD = '$updateData[$key] = $data[$key] ?? config("units.base_units.{$type}");'
WRITE_NEW = (
    'app(\\App\\Services\\UnitConversion\\PreferenceRepository::class)\n'
    '                    ->setPreferredUnit($type, $data[$key] ?? config("units.base_units.{$type}"), $user);'
)

ANCHORS = [
    ('validation rule uses active units', RULE_OLD, RULE_NEW),
    ('preference write goes through the repository', WRITE_OLD, WRITE_NEW),
]


def main() -> int:
    dry = '--dry-run' in sys.argv

    try:
        source = open(PATH, encoding='utf-8').read()
    except FileNotFoundError:
        print('FAIL: %s not found. Run this from the application root.' % PATH)
        return 1

    if 'UnitConversion\\PreferenceRepository' in source and RULE_NEW in source:
        print('Already patched. Nothing to do.')
        return 0

    problems = []
    for label, old, _ in ANCHORS:
        n = source.count(old)
        print('  %-46s anchor found: %d' % (label, n))
        if n != 1:
            problems.append('%s matched %d times, expected 1' % (label, n))

    if problems:
        print()
        for p in problems:
            print('FAIL: ' + p)
        print('Nothing was changed.')
        return 1

    if dry:
        print('\nDry run only. Both anchors match. Nothing written.')
        return 0

    patched = source
    for _, old, new in ANCHORS:
        patched = patched.replace(old, new)

    open(PATH + '.pre_m2m3', 'w', encoding='utf-8').write(source)
    open(PATH, 'w', encoding='utf-8').write(patched)

    print('\nPatched %s' % PATH)
    print('  backup: %s.pre_m2m3' % PATH)
    return 0


if __name__ == '__main__':
    sys.exit(main())
