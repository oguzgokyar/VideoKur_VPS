#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Test Subtitle Color Conversion and Style Building
Verifies that hex colors are properly converted to ASS format
and subtitle styles are correctly built for FFmpeg
"""
import sys
import os

# Add python directory to path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python'))

from video_composer import _hex_to_ass, build_subtitle_style, SUBTITLE_PRESETS


def test_hex_to_ass_conversion():
    """Test hex color to ASS format conversion"""
    print("=" * 60)
    print("Test 1: Hex to ASS Color Conversion")
    print("=" * 60)
    
    test_cases = {
        '#FFFFFF': '&H00FFFFFF',  # White
        '#000000': '&H00000000',  # Black
        '#FFFF00': '&H0000FFFF',  # Yellow (note: BGR format!)
        '#FF0000': '&H000000FF',  # Red → Blue in ASS (BGR)
        '#00FF00': '&H0000FF00',  # Green
        '#0000FF': '&H00FF0000',  # Blue → Red in ASS (BGR)
    }
    
    passed = 0
    failed = 0
    
    for hex_color, expected_ass in test_cases.items():
        result = _hex_to_ass(hex_color)
        if result == expected_ass:
            print(f"✅ PASS: {hex_color} → {result}")
            passed += 1
        else:
            print(f"❌ FAIL: {hex_color} → {result} (expected: {expected_ass})")
            failed += 1
    
    print(f"\nResults: {passed} passed, {failed} failed\n")
    return failed == 0


def test_build_style_string():
    """Test building FFmpeg force_style string from dict"""
    print("=" * 60)
    print("Test 2: Build Style String")
    print("=" * 60)
    
    test_style = {
        'FontName': 'Arial',
        'FontSize': 24,
        'PrimaryColour': '#FFFFFF',
        'OutlineColour': '#000000',
        'Outline': 2,
        'Shadow': 1,
        'MarginV': 100,
        'Bold': 1,
    }
    
    style_str = build_subtitle_style(test_style)
    print(f"Style dict: {test_style}")
    print(f"Style string: {style_str}")
    
    # Verify expected components
    expected_components = [
        'FontName=Arial',
        'FontSize=24',
        'PrimaryColour=&H00FFFFFF',
        'OutlineColour=&H00000000',
        'Outline=2',
        'Shadow=1',
        'MarginV=100',
        'Bold=1',
    ]
    
    passed = True
    for component in expected_components:
        if component in style_str:
            print(f"✅ Contains: {component}")
        else:
            print(f"❌ Missing: {component}")
            passed = False
    
    print()
    return passed


def test_preset_styles():
    """Test all preset subtitle styles"""
    print("=" * 60)
    print("Test 3: Preset Styles")
    print("=" * 60)
    
    for preset_name, preset_style in SUBTITLE_PRESETS.items():
        style_str = build_subtitle_style(preset_style)
        print(f"\nPreset: {preset_name}")
        print(f"  Config: {preset_style}")
        print(f"  Style:  {style_str[:80]}{'...' if len(style_str) > 80 else ''}")
    
    print(f"\nTotal presets: {len(SUBTITLE_PRESETS)}")
    print()
    return True


def test_config_subtitle_style():
    """Test loading and converting config.json subtitle style"""
    print("=" * 60)
    print("Test 4: Config Subtitle Style")
    print("=" * 60)
    
    config_path = os.path.join(os.path.dirname(__file__), 'data', 'config.json')
    
    if not os.path.exists(config_path):
        print("⚠️  Config file not found, skipping test")
        return True
    
    import json
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)
        
        subtitle_style = config.get('subtitleStyle')
        if not subtitle_style:
            print("⚠️  No subtitleStyle in config, using classic preset")
            subtitle_style = SUBTITLE_PRESETS['classic']
        
        print(f"Config subtitle style: {subtitle_style}")
        style_str = build_subtitle_style(subtitle_style)
        print(f"Converted to FFmpeg style: {style_str}")
        print("✅ Config subtitle style loaded and converted successfully")
        return True
        
    except Exception as e:
        print(f"❌ Error loading config: {e}")
        return False


def main():
    """Run all tests"""
    print("\n" + "=" * 60)
    print("SUBTITLE COLOR & STYLE TEST SUITE")
    print("=" * 60 + "\n")
    
    results = []
    
    # Run all tests
    results.append(("Hex to ASS Conversion", test_hex_to_ass_conversion()))
    results.append(("Build Style String", test_build_style_string()))
    results.append(("Preset Styles", test_preset_styles()))
    results.append(("Config Style", test_config_subtitle_style()))
    
    # Summary
    print("=" * 60)
    print("TEST SUMMARY")
    print("=" * 60)
    
    passed = sum(1 for _, result in results if result)
    total = len(results)
    
    for test_name, result in results:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{status}: {test_name}")
    
    print(f"\n{passed}/{total} test suites passed")
    
    if passed == total:
        print("\n🎉 All tests passed!")
        return 0
    else:
        print(f"\n⚠️  {total - passed} test suite(s) failed")
        return 1


if __name__ == '__main__':
    sys.exit(main())
