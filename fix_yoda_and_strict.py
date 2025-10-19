#!/usr/bin/env python3
import re

def fix_yoda_conditions(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    modified = False
    for i, line in enumerate(lines):
        # Fix Yoda conditions: $var === 'value' to 'value' === $var
        new_line = re.sub(
            r'\$(\w+)\s*===\s*(["\'][^"\']*["\'])',
            r'\2 === $\1',
            line
        )
        
        # Add true parameter to in_array
        new_line = re.sub(
            r'in_array\(\s*([^,]+),\s*([^\)]+)\s*\)(?!\s*,\s*true)',
            r'in_array( \1, \2, true )',
            new_line
        )
        
        if new_line != line:
            lines[i] = new_line
            modified = True
    
    if modified:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(lines)
        return True
    return False

# Files with Yoda condition errors
files_to_fix = [
    'src/Frontend/class-registrationhandler.php',
    'src/Frontend/class-registrationshortcode.php',
    'src/Security/class-ratelimit.php',
    'src/class-plugin.php',
    'src/DB/class-reviewstable.php',
    'src/DB/class-locationstable.php',
    'src/DB/class-claimrequeststable.php',
    'src/API/class-submissionendpoint.php',
    'src/REST/class-claimcontroller.php',
    'src/REST/class-submitreviewcontroller.php',
    'src/Admin/class-settings.php',
    'src/Admin/class-importerpage.php',
    'src/Search/class-querybuilder.php',
    'business-directory.php',
    'src/Gamification/class-badgesystem.php',
    'src/Gamification/class-activitytracker.php',
    'src/Frontend/class-profileshortcode.php',
    'src/Frontend/class-badgedisplay.php',
    'src/DB/class-installer.php',
]

count = 0
for filepath in files_to_fix:
    try:
        if fix_yoda_conditions(filepath):
            print(f'Fixed: {filepath}')
            count += 1
    except FileNotFoundError:
        print(f'Skipped (not found): {filepath}')

print(f'\n✅ Fixed {count} files!')
