#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de gestion et synchronisation des emplois du temps
Vérifie la cohérence des données et peut nettoyer les fichiers JSON
"""

import json
import os
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Tuple

PLANNINGS_DIR = Path(__file__).parent / "plannings"

def validate_event(event: dict) -> Tuple[bool, List[str]]:
    """Valide un événement et retourne (is_valid, errors)"""
    errors = []
    
    if not isinstance(event, dict):
        return False, ["Event is not a dictionary"]
    
    # Champs requis
    required_fields = ['id', 'title', 'start', 'end', 'className']
    for field in required_fields:
        if field not in event:
            errors.append(f"Missing required field: {field}")
        elif field in ['title', 'start', 'end', 'className'] and not isinstance(event[field], str):
            errors.append(f"Field '{field}' must be string, got {type(event[field]).__name__}")
    
    # Valide le format ISO de start et end
    for time_field in ['start', 'end']:
        if time_field in event and isinstance(event[time_field], str):
            try:
                datetime.fromisoformat(event[time_field].replace('Z', '+00:00'))
            except ValueError:
                errors.append(f"Invalid ISO format for {time_field}: {event[time_field]}")
    
    return len(errors) == 0, errors

def load_planning_file(filepath: Path) -> Tuple[bool, dict, List[str]]:
    """Charge un fichier de planning et valide sa structure"""
    errors = []
    
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except json.JSONDecodeError as e:
        return False, {}, [f"JSON decode error: {e}"]
    except Exception as e:
        return False, {}, [f"Error loading file: {e}"]
    
    if not isinstance(data, dict):
        return False, {}, ["Root element must be a dictionary"]
    
    # Extrait les événements
    if 'events' in data:
        events = data.get('events', [])
    else:
        # Si pas de clé 'events', le root doit être un tableau d'événements
        events = data if isinstance(data, list) else []
    
    if not isinstance(events, list):
        return False, {}, ["'events' field must be an array"]
    
    # Valide chaque événement
    valid_events = []
    for idx, event in enumerate(events):
        is_valid, event_errors = validate_event(event)
        if is_valid:
            valid_events.append(event)
        else:
            errors.append(f"Event {idx}: {'; '.join(event_errors)}")
    
    result = {
        'total_events': len(events),
        'valid_events': len(valid_events),
        'invalid_events': len(events) - len(valid_events),
        'events': valid_events
    }
    
    return True, result, errors

def analyze_planning_files() -> None:
    """Analyse tous les fichiers de planning"""
    
    if not PLANNINGS_DIR.exists():
        print(f"❌ Directory not found: {PLANNINGS_DIR}")
        return
    
    print("=" * 70)
    print("Synchronisation des emplois du temps - Rapport d'analyse")
    print("=" * 70)
    print()
    
    files = sorted([f for f in PLANNINGS_DIR.glob("*.json")])
    print(f"📁 Répertoire: {PLANNINGS_DIR}")
    print(f"📊 Fichiers trouvés: {len(files)}")
    print()
    
    stats = {
        'total_files': len(files),
        'valid_files': 0,
        'total_events': 0,
        'valid_events': 0,
        'errors_count': 0,
    }
    
    all_errors = []
    file_details = []
    
    for filepath in files:
        filename = filepath.name
        is_valid, data, errors = load_planning_file(filepath)
        
        if not is_valid:
            status = "❌"
            stats['errors_count'] += len(errors)
            for error in errors:
                all_errors.append(f"{filename}: {error}")
        else:
            status = "✅" if data['invalid_events'] == 0 else "⚠️ "
            stats['valid_files'] += 1
            stats['total_events'] += data['total_events']
            stats['valid_events'] += data['valid_events']
            stats['errors_count'] += len(errors)
            
            if errors:
                for error in errors:
                    all_errors.append(f"{filename}: {error}")
            
            file_details.append({
                'name': filename,
                'status': status,
                'total': data['total_events'],
                'valid': data['valid_events'],
                'invalid': data['invalid_events'],
            })
    
    # Affiche les détails par fichier
    print("📋 Fichiers valides:")
    print("-" * 70)
    for detail in file_details:
        if detail['total'] > 0:
            pct = (detail['valid'] / detail['total'] * 100) if detail['total'] > 0 else 0
            print(f"{detail['status']} {detail['name']:<50} {detail['valid']:>4}/{detail['total']:<4} ({pct:>5.1f}%)")
    
    print()
    print("📊 Résumé global:")
    print("-" * 70)
    print(f"  ✅ Fichiers valides: {stats['valid_files']}/{stats['total_files']}")
    print(f"  📅 Événements totaux: {stats['total_events']}")
    print(f"  ✅ Événements valides: {stats['valid_events']}")
    print(f"  ⚠️  Événements invalides: {stats['total_events'] - stats['valid_events']}")
    
    if all_errors:
        print()
        print("⚠️  Erreurs et avertissements:")
        print("-" * 70)
        for error in all_errors[:20]:  # Affiche les 20 premières erreurs
            print(f"  • {error}")
        if len(all_errors) > 20:
            print(f"  ... et {len(all_errors) - 20} autres erreurs")
    else:
        print()
        print("✅ Aucune erreur détectée!")
    
    print()
    print("=" * 70)

if __name__ == "__main__":
    analyze_planning_files()
