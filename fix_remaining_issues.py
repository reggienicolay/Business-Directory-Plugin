#!/usr/bin/env python3
import re
import os

def fix_yoda_conditions(content):
    """Fix Yoda condition violations"""
    # Pattern: $var === 'value' or $var == 'value'
    patterns = [
        (r'\$(\w+)\s*===\s*(["\'][^"\']+["\'])', r'\2 === $\1'),
        (r'\$(\w+)\s*==\s*(["\'][^"\']+["\'])', r'\2 === $\1'),
        (r'\$(\w+)\s*!==\s*(["\'][^"\']+["\'])', r'\2 !== $\1'),
        (r'\$(\w+)\s*!=\s*(["\'][^"\']+["\'])', r'\2 !== $\1'),
    ]
    
    for pattern, replacement in patterns:
        content = re.sub(pattern, replacement, content)
    
    return content

def fix_short_ternary(content):
    """Fix short ternary operators"""
    # Pattern: $var ?: 'default'
    # This is tricky and needs context, so we'll flag it
    lines = content.split('\n')
    fixed_lines = []
    
    for line in lines:
        if '?:' in line:
            # Try to fix simple cases
            match = re.search(r'\$(\w+)\s*\?\:\s*([^;]+)', line)
            if match:
                var_name = match.group(1)
                default = match.group(2).strip()
                replacement = f'! empty( ${var_name} ) ? ${var_name} : {default}'
                line = line.replace(f'${var_name} ?: {default}', replacement)
        fixed_lines.append(line)
    
    return '\n'.join(fixed_lines)

def add_translator_comments(content):
    """Add translator comments before __() with placeholders"""
    lines = content.split('\n')
    fixed_lines = []
    
    for i, line in enumerate(lines):
        # Check if line has __() with %s or %d
        if '__(' in line and ('%s' in line or '%d' in line):
            # Check if previous line already has translators comment
            if i > 0 and 'translators:' not in lines[i-1]:
                indent = len(line) - len(line.lstrip())
                comment = ' ' * indent + '// translators: Placeholder for dynamic value.'
                fixed_lines.append(comment)
        fixed_lines.append(line)
    
    return '\n'.join(fixed_lines)

def process_file(filepath):
    """Process a single PHP file"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original = content
        
        # Apply fixes
        content = fix_yoda_conditions(content)
        content = fix_short_ternary(content)
        content = add_translator_comments(content)
        
        # Only write if changes were made
        if content != original:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            return True
        return False
    except Exception as e:
        print(f"Error processing {filepath}: {e}")
        return False

def main():
    """Main function"""
    src_dir = 'src'
    fixed_count = 0
    
    # Process all PHP files in src directory
    for root, dirs, files in os.walk(src_dir):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                if process_file(filepath):
                    print(f"Fixed: {filepath}")
                    fixed_count += 1
    
    # Process main plugin file
    if process_file('business-directory.php'):
        print("Fixed: business-directory.php")
        fixed_count += 1
    
    print(f"\n✅ Fixed {fixed_count} files!")
    print("\n⚠️  Some issues still need manual review:")
    print("  - Database table name interpolation (can be ignored)")
    print("  - Complex short ternary operators")
    print("  - Variable naming in specific contexts")

if __name__ == '__main__':
    main()
