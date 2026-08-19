#!/usr/bin/env python3
import json
import os

languages = ['en', 'ar', 'bn', 'de', 'ja']
base = 'web/src/i18n/messages'

for lang in languages:
    path = f'{base}/{lang}.json'
    print(f'Updating {path}...')
    with open(path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    data['retention'] = {
        'days': 'Retention days',
        'anonymize': 'Anonymize customer',
        'confirm': 'Are you sure you want to anonymize this customer? This will hash all PII but keep financial records.'
    }
    
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f'  Updated {path}')

print('All done!')