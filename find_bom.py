#!/usr/bin/env python3
import os
from pathlib import Path

# UTF-8 BOM bytes
BOM_UTF8 = b'\xef\xbb\xbf'

def find_files_with_bom(directories):
    """Find all PHP files with UTF-8 BOM in given directories"""
    results = {
        'wp-content/plugins': [],
        'wp-content/themes': [],
        'wp-content/mu-plugins': [],
        'wp-admin': []
    }
    
    for dir_key, dir_path in [
        ('wp-content/plugins', 'wp-content\\plugins'),
        ('wp-content/themes', 'wp-content\\themes'),
        ('wp-content/mu-plugins', 'wp-content\\mu-plugins'),
        ('wp-admin', 'wp-admin')
    ]:
        if not os.path.exists(dir_path):
            print(f"Directory {dir_path} not found")
            continue
            
        print(f"Scanning {dir_key}...")
        for root, dirs, files in os.walk(dir_path):
            for file in files:
                if file.endswith('.php'):
                    file_path = os.path.join(root, file)
                    try:
                        with open(file_path, 'rb') as f:
                            first_bytes = f.read(3)
                            if first_bytes == BOM_UTF8:
                                # Determine if system or custom file
                                is_system = 'wpcargo' not in file_path.lower() and 'blocksy' not in file_path.lower()
                                results[dir_key].append({
                                    'path': file_path,
                                    'is_system': is_system
                                })
                    except Exception as e:
                        pass
    
    return results

if __name__ == '__main__':
    results = find_files_with_bom([
        'wp-content\\plugins',
        'wp-content\\themes',
        'wp-content\\mu-plugins',
        'wp-admin'
    ])
    
    print("\n" + "="*80)
    print("FILES WITH UTF-8 BOM (EF BB BF)")
    print("="*80)
    
    total = 0
    for dir_name, files in results.items():
        if files:
            print(f"\n{dir_name}: {len(files)} file(s)")
            print("-" * 80)
            for item in files:
                file_type = "SYSTEM FILE" if item['is_system'] else "CUSTOM FILE"
                rel_path = os.path.relpath(item['path'])
                print(f"  Path: {rel_path}")
                print(f"  Type: {file_type}")
                print()
                total += 1
    
    print("="*80)
    print(f"TOTAL: {total} file(s) with BOM found")
    print("="*80)
