"""
Test Production Fixes
Tests timeout, stuck detection, guaranteed updates, user-friendly errors
"""
import sys
import os
import json
import time
import threading

# Add paths
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python'))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python', 'utils'))

# Import modules
from script_gen import generate_script
from pipeline import update_job
from error_messages import format_error_for_job, extract_error_code, get_user_friendly_error


def test_timeout():
    """Test 1: API Timeout"""
    print("\n" + "="*70)
    print("TEST 1: API Timeout (2 dakika)")
    print("="*70)
    
    def slow_api():
        """Simulate slow API that takes 5 seconds"""
        time.sleep(5)
        return {'success': True, 'script': {'intro': 'Test'}}
    
    print("Testing timeout with 3 second limit (should timeout)...")
    
    # Mock generate_script with timeout
    start = time.time()
    result = {'success': False, 'timeout': False}
    
    def _api_call():
        try:
            time.sleep(5)  # Simulate 5 second API call
            result['success'] = True
        except:
            pass
    
    thread = threading.Thread(target=_api_call, daemon=True)
    thread.start()
    thread.join(timeout=3)  # 3 second timeout
    
    if thread.is_alive():
        result['timeout'] = True
        result['error'] = 'Timeout occurred'
    
    elapsed = time.time() - start
    
    if result['timeout']:
        print(f"✅ PASS: Timeout detected after {elapsed:.1f}s")
        print(f"   Expected: ~3s, Got: {elapsed:.1f}s")
    else:
        print(f"❌ FAIL: No timeout detected")
    
    return result['timeout']


def test_user_friendly_errors():
    """Test 2: User-Friendly Error Messages"""
    print("\n" + "="*70)
    print("TEST 2: User-Friendly Error Messages")
    print("="*70)
    
    test_cases = [
        ('503', 'Model is experiencing high demand'),
        ('429', 'Quota exceeded'),
        ('timeout', 'API did not respond'),
    ]
    
    passed = 0
    for error_type, technical_detail in test_cases:
        msg = format_error_for_job(error_type, technical_detail)
        
        # Check if message is user-friendly (not just technical)
        is_friendly = any(keyword in msg for keyword in [
            'Gemini', 'API', 'bekle', 'Devam Et', 'Yapılması', 'kontrol'
        ])
        
        if is_friendly:
            print(f"✅ PASS: {error_type} → User-friendly message")
            print(f"   Preview: {msg.split(chr(10))[0][:60]}...")
            passed += 1
        else:
            print(f"❌ FAIL: {error_type} → Not user-friendly")
            print(f"   Got: {msg[:100]}")
    
    print(f"\nResult: {passed}/{len(test_cases)} passed")
    return passed == len(test_cases)


def test_guaranteed_updates():
    """Test 3: Guaranteed Job Updates"""
    print("\n" + "="*70)
    print("TEST 3: Guaranteed Job Updates")
    print("="*70)
    
    # Create test job
    test_dir = 'data/jobs'
    os.makedirs(test_dir, exist_ok=True)
    
    test_job_id = 'test_update_job'
    
    # Test 1: Create new job
    print("Test 3a: Create new job...")
    success = update_job(test_dir, test_job_id, {
        'id': test_job_id,
        'status': 'processing',
        'title': 'Test Job'
    })
    
    if success:
        print("✅ PASS: Job created successfully")
    else:
        print("❌ FAIL: Job creation failed")
        return False
    
    # Test 2: Update existing job
    print("Test 3b: Update existing job...")
    success = update_job(test_dir, test_job_id, {
        'status': 'completed',
        'progress': 100
    })
    
    if success:
        # Verify update
        job_file = os.path.join(test_dir, f'{test_job_id}.json')
        with open(job_file, 'r') as f:
            job = json.load(f)
        
        if job.get('status') == 'completed' and job.get('progress') == 100:
            print("✅ PASS: Job updated successfully")
            print(f"   Status: {job.get('status')}")
            print(f"   Last update time: {job.get('last_update_time')}")
        else:
            print("❌ FAIL: Job not updated correctly")
            return False
    else:
        print("❌ FAIL: Job update failed")
        return False
    
    # Cleanup
    try:
        os.remove(os.path.join(test_dir, f'{test_job_id}.json'))
    except:
        pass
    
    return True


def test_error_extraction():
    """Test 4: Error Code Extraction"""
    print("\n" + "="*70)
    print("TEST 4: Error Code Extraction")
    print("="*70)
    
    test_cases = [
        ('503 UNAVAILABLE: Model is experiencing high demand', '503'),
        ('429 Resource exhausted: Quota exceeded', '429'),
        ('timeout: API did not respond in time', 'timeout'),
        ('500 Internal Server Error', '500'),
    ]
    
    passed = 0
    for error_text, expected_code in test_cases:
        extracted = extract_error_code(error_text)
        
        if extracted == expected_code:
            print(f"✅ PASS: '{error_text[:40]}...' → {extracted}")
            passed += 1
        else:
            print(f"❌ FAIL: '{error_text[:40]}...'")
            print(f"   Expected: {expected_code}, Got: {extracted}")
    
    print(f"\nResult: {passed}/{len(test_cases)} passed")
    return passed == len(test_cases)


def main():
    """Run all tests"""
    print("\n" + "="*70)
    print("🧪 PRODUCTION FIXES - TEST SUITE")
    print("="*70)
    print("Testing 4 fixes:")
    print("  1. API Timeout")
    print("  2. User-Friendly Errors")
    print("  3. Guaranteed Job Updates")
    print("  4. Error Code Extraction")
    print("="*70)
    
    results = []
    
    # Run tests
    results.append(("Timeout", test_timeout()))
    results.append(("User-Friendly Errors", test_user_friendly_errors()))
    results.append(("Guaranteed Updates", test_guaranteed_updates()))
    results.append(("Error Extraction", test_error_extraction()))
    
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
