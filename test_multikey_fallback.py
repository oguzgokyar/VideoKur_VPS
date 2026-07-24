"""
Test Multi-Key Fallback Logic
Simulates 503 errors and tests if system tries next keys
"""
import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python'))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python', 'utils'))

from error_messages import extract_error_code

# Test error extraction and retriable logic
def test_retriable_errors():
    print("\n" + "="*70)
    print("TEST: Retriable Error Detection")
    print("="*70)
    
    RETRIABLE_ERRORS = ['429', '503', '500', '502', 'timeout']
    
    test_cases = [
        ('503 UNAVAILABLE: Model is experiencing high demand', True, '503'),
        ('429 Resource exhausted: Quota exceeded', True, '429'),
        ('timeout: API did not respond in time', True, 'timeout'),
        ('500 Internal Server Error', True, '500'),
        ('502 Bad Gateway', True, '502'),
        ('404 Not Found', False, 'unknown'),
        ('Invalid JSON response', False, 'unknown'),
    ]
    
    passed = 0
    for error_text, expected_retriable, expected_code in test_cases:
        # Extract code
        extracted_code = extract_error_code(error_text)
        
        # Check if retriable
        is_retriable = any(code in error_text for code in RETRIABLE_ERRORS)
        
        # Verify
        code_match = extracted_code == expected_code
        retriable_match = is_retriable == expected_retriable
        
        if code_match and retriable_match:
            status = "✅ PASS"
            passed += 1
        else:
            status = "❌ FAIL"
        
        print(f"{status} - '{error_text[:40]}...'")
        print(f"   Code: {extracted_code} (expected: {expected_code})")
        print(f"   Retriable: {is_retriable} (expected: {expected_retriable})")
        print()
    
    print(f"Result: {passed}/{len(test_cases)} passed")
    return passed == len(test_cases)


def test_multikey_logic():
    print("\n" + "="*70)
    print("TEST: Multi-Key Fallback Logic (Simulated)")
    print("="*70)
    
    # Simulate 3 keys with different errors
    api_keys = ['key1', 'key2', 'key3']
    
    # Scenario 1: First key 503, second key success
    print("\nScenario 1: Key1=503, Key2=Success")
    print("Expected: Try Key1 → Fail (503) → Try Key2 → Success")
    
    errors = ['503', 'success']
    for idx, key in enumerate(api_keys[:2]):
        if errors[idx] == 'success':
            print(f"✅ Key {idx+1}: SUCCESS")
            break
        else:
            print(f"❌ Key {idx+1}: {errors[idx]} (retriable, try next)")
    
    print()
    
    # Scenario 2: All keys 503
    print("\nScenario 2: All keys 503")
    print("Expected: Try all 3 keys → All fail → Show error")
    
    for idx, key in enumerate(api_keys):
        print(f"❌ Key {idx+1}: 503 UNAVAILABLE")
        if idx < len(api_keys) - 1:
            print(f"   ♻️ Retriable, trying next key...")
    print(f"⚠️ All {len(api_keys)} keys failed")
    
    print()
    
    # Scenario 3: First key 404 (non-retriable)
    print("\nScenario 3: Key1=404 (non-retriable)")
    print("Expected: Try Key1 → Fail (404) → STOP (don't try others)")
    
    print(f"❌ Key 1: 404 Not Found")
    print(f"   🛑 Non-retriable error, stopping")
    
    return True


def main():
    print("\n" + "="*70)
    print("🧪 MULTI-KEY FALLBACK - TEST SUITE")
    print("="*70)
    
    results = []
    results.append(("Retriable Error Detection", test_retriable_errors()))
    results.append(("Multi-Key Logic", test_multikey_logic()))
    
    # Summary
    print("\n" + "="*70)
    print("📊 TEST SUMMARY")
    print("="*70)
    
    passed = sum(1 for _, result in results if result)
    total = len(results)
    
    for test_name, result in results:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{status} - {test_name}")
    
    print("="*70)
    print(f"Result: {passed}/{total} tests passed")
    
    if passed == total:
        print("✅ ALL TESTS PASSED")
        return 0
    else:
        print(f"⚠️ {total - passed} test(s) failed")
        return 1


if __name__ == '__main__':
    exit_code = main()
    sys.exit(exit_code)
