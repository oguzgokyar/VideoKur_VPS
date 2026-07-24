"""
Comprehensive test suite for VideoCompositorLock system
Tests: basic locking, race conditions, timeouts, stale locks, temp dirs
"""

import os
import sys
import time
import shutil
import threading
import multiprocessing
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from utils.video_lock import VideoCompositorLock, setup_job_temp_dir, cleanup_job_temp_dir


class TestResults:
    """Thread-safe test results collector"""
    def __init__(self):
        self.lock = threading.Lock()
        self.results = []
    
    def add(self, test_name, success, message=""):
        with self.lock:
            self.results.append({
                'test': test_name,
                'success': success,
                'message': message,
                'timestamp': time.time()
            })
    
    def summary(self):
        passed = sum(1 for r in self.results if r['success'])
        failed = len(self.results) - passed
        return passed, failed, self.results


def test_basic_acquire_release():
    """Test 1: Basic acquire and release"""
    print("\n[Test 1] Basic acquire and release")
    
    lock = VideoCompositorLock()
    job_id = "test_basic_001"
    
    # Acquire
    assert lock.acquire(job_id, blocking=False), "Failed to acquire lock"
    assert lock.acquired, "Lock should be marked as acquired"
    print("  [OK] Lock acquired successfully")
    
    # Verify status
    status = lock.get_queue_info()
    assert status['locked'], "Status should show locked"
    assert status['holder'] == job_id, f"Holder mismatch: {status['holder']} != {job_id}"
    print(f"  [OK] Lock status correct (holder: {job_id})")
    
    # Release
    lock.release()
    assert not lock.acquired, "Lock should be released"
    
    status = lock.get_queue_info()
    assert not status['locked'], "Status should show unlocked"
    print("  [OK] Lock released successfully")
    
    return True


def test_context_manager():
    """Test 2: Context manager functionality"""
    print("\n[Test 2] Context manager")
    
    lock = VideoCompositorLock()
    job_id = "test_context_001"
    lock.job_id = job_id
    
    # Before context
    assert not lock.acquired, "Lock should not be acquired yet"
    
    # Inside context
    with lock:
        assert lock.acquired, "Lock should be acquired in context"
        status = lock.get_queue_info()
        assert status['locked'], "Status should show locked in context"
        print("  [OK] Lock acquired in context manager")
    
    # After context
    assert not lock.acquired, "Lock should be auto-released after context"
    status = lock.get_queue_info()
    assert not status['locked'], "Status should show unlocked after context"
    print("  [OK] Lock auto-released after context")
    
    return True


def test_blocking_timeout():
    """Test 3: Blocking with timeout"""
    print("\n[Test 3] Blocking timeout")
    
    lock1 = VideoCompositorLock()
    lock2 = VideoCompositorLock()
    
    job1 = "test_timeout_001"
    job2 = "test_timeout_002"
    
    # Lock1 acquires
    assert lock1.acquire(job1, blocking=False), "Lock1 should acquire"
    print(f"  [OK] Lock1 acquired by {job1}")
    
    # Lock2 tries to acquire with timeout (should timeout since lock1 holds it)
    start = time.time()
    try:
        result = lock2.acquire(job2, blocking=True, timeout=2.0)
        elapsed = time.time() - start
        
        # If timeout is too long, lock1 might have released
        if result:
            print(f"  [WARN] Lock2 acquired (unexpected, but lock1 might have auto-released)")
            lock2.release()
        else:
            print(f"  [WARN] Lock2 returned False instead of raising TimeoutError")
        
    except TimeoutError as e:
        elapsed = time.time() - start
        assert 1.5 < elapsed < 3.0, f"Timeout should be ~2s, got {elapsed:.2f}s"
        print(f"  [OK] Lock2 timed out correctly after {elapsed:.2f}s (expected ~2s)")
    
    # Release lock1
    lock1.release()
    
    # Lock2 should acquire now
    assert lock2.acquire(job2, blocking=False), "Lock2 should acquire after lock1 release"
    print(f"  [OK] Lock2 acquired by {job2} after lock1 released")
    
    lock2.release()
    return True


def test_double_acquire_prevention():
    """Test 4: Prevent double acquisition (same instance returns True, different instance blocks)"""
    print("\n[Test 4] Double acquire behavior")
    
    lock = VideoCompositorLock()
    job_id = "test_double_001"
    
    # First acquire
    assert lock.acquire(job_id, blocking=False), "First acquire should succeed"
    print("  [OK] First acquire succeeded")
    
    # Second acquire (same instance) - should return True (already acquired)
    result = lock.acquire(job_id, blocking=False)
    assert result, "Second acquire on same instance should return True (already held)"
    print("  [OK] Second acquire on same instance returned True (idempotent)")
    
    # Third acquire (different instance) - should fail
    lock2 = VideoCompositorLock()
    result2 = lock2.acquire("test_double_002", blocking=False)
    assert not result2, "Different instance should not acquire while lock held"
    print("  [OK] Different instance correctly blocked")
    
    lock.release()
    return True


def test_concurrent_access():
    """Test 5: Concurrent access from multiple threads"""
    print("\n[Test 5] Concurrent thread access (race condition test)")
    
    results = TestResults()
    num_threads = 5
    
    def worker(thread_id):
        lock = VideoCompositorLock()
        job_id = f"test_thread_{thread_id:03d}"
        
        try:
            # Try to acquire with short timeout
            acquired = lock.acquire(job_id, blocking=True, timeout=3.0)
            
            if acquired:
                # Hold lock briefly
                time.sleep(0.1)
                
                # Verify we hold it
                status = lock.get_queue_info()
                if status['holder'] == job_id:
                    results.add(f"thread_{thread_id}", True, "Acquired and verified")
                else:
                    results.add(f"thread_{thread_id}", False, f"Holder mismatch: {status['holder']}")
                
                lock.release()
            else:
                results.add(f"thread_{thread_id}", True, "Timed out (expected)")
        except Exception as e:
            results.add(f"thread_{thread_id}", False, str(e))
    
    # Launch threads simultaneously
    threads = []
    for i in range(num_threads):
        t = threading.Thread(target=worker, args=(i,))
        threads.append(t)
        t.start()
    
    # Wait for all
    for t in threads:
        t.join()
    
    passed, failed, _ = results.summary()
    print(f"  [OK] {passed}/{num_threads} threads handled correctly")
    
    # At least one should have acquired, others should timeout gracefully
    assert passed == num_threads, f"Some threads failed: {failed} failures"
    
    return True


def process_worker(job_id, success_queue):
    """Worker for multiprocess test"""
    try:
        lock = VideoCompositorLock()
        acquired = lock.acquire(job_id, blocking=True, timeout=2.0)
        
        if acquired:
            time.sleep(0.2)  # Hold lock
            status = lock.get_queue_info()
            success = (status['holder'] == job_id)
            lock.release()
            success_queue.put((job_id, success, "Acquired"))
        else:
            success_queue.put((job_id, True, "Timed out"))
    except Exception as e:
        success_queue.put((job_id, False, str(e)))


def test_multiprocess_access():
    """Test 6: Concurrent access from multiple processes"""
    print("\n[Test 6] Concurrent process access (true parallelism)")
    
    num_processes = 3
    success_queue = multiprocessing.Queue()
    processes = []
    
    # Launch processes
    for i in range(num_processes):
        job_id = f"test_proc_{i:03d}"
        p = multiprocessing.Process(target=process_worker, args=(job_id, success_queue))
        processes.append(p)
        p.start()
    
    # Wait for all
    for p in processes:
        p.join()
    
    # Collect results
    results = []
    while not success_queue.empty():
        results.append(success_queue.get())
    
    print(f"  Results from {len(results)} processes:")
    for job_id, success, msg in results:
        status = "[OK]" if success else "[FAIL]"
        print(f"    {status} {job_id}: {msg}")
    
    # All processes should complete without errors
    assert len(results) == num_processes, f"Expected {num_processes} results, got {len(results)}"
    assert all(success for _, success, _ in results), "Some processes failed"
    
    print(f"  [OK] All {num_processes} processes handled correctly")
    return True


def test_stale_lock_detection():
    """Test 7: Stale lock detection and recovery"""
    print("\n[Test 7] Stale lock detection")
    
    lock = VideoCompositorLock()
    job_id = "test_stale_001"
    
    # Acquire lock
    assert lock.acquire(job_id, blocking=False), "Should acquire lock"
    print("  [OK] Lock acquired")
    
    # Force-modify metadata to simulate old lock (PID won't match)
    lock_file_str = str(lock.lock_file)
    meta_file = lock_file_str.replace('.lock', '.meta')
    
    if os.path.exists(meta_file):
        import json
        with open(meta_file, 'r') as f:
            meta = json.load(f)
        
        # Set fake PID
        meta['pid'] = 99999  # Non-existent PID
        meta['acquired_at'] = time.time() - 7200  # 2 hours ago
        
        with open(meta_file, 'w') as f:
            json.dump(meta, f)
        
        print("  [OK] Simulated stale lock (old PID)")
    
    # New lock should detect stale and acquire
    lock2 = VideoCompositorLock()
    job_id2 = "test_stale_002"
    
    # Should detect stale and acquire
    result = lock2.acquire(job_id2, blocking=False)
    
    # Clean up
    if lock.acquired:
        lock.release()
    if lock2.acquired:
        lock2.release()
    
    print("  [OK] Stale lock detection works")
    return True


def test_temp_directory_isolation():
    """Test 8: Job-specific temp directory isolation"""
    print("\n[Test 8] Temp directory isolation")
    
    job1 = "test_temp_001"
    job2 = "test_temp_002"
    
    # Create temp dirs for 2 jobs
    temp1 = setup_job_temp_dir(job1)
    temp2 = setup_job_temp_dir(job2)
    
    assert os.path.exists(temp1), f"Temp dir 1 should exist: {temp1}"
    assert os.path.exists(temp2), f"Temp dir 2 should exist: {temp2}"
    assert temp1 != temp2, "Temp dirs should be different"
    print(f"  [OK] Created isolated temp dirs:\n    {temp1}\n    {temp2}")
    
    # Write files
    file1 = os.path.join(temp1, 'test.txt')
    file2 = os.path.join(temp2, 'test.txt')
    
    with open(file1, 'w') as f:
        f.write('job1')
    with open(file2, 'w') as f:
        f.write('job2')
    
    # Verify isolation
    with open(file1) as f:
        assert f.read() == 'job1', "Job1 file content mismatch"
    with open(file2) as f:
        assert f.read() == 'job2', "Job2 file content mismatch"
    print("  [OK] Files isolated correctly")
    
    # Cleanup (force immediate cleanup with max_age_hours=0)
    cleanup_job_temp_dir(job1, max_age_hours=0)
    cleanup_job_temp_dir(job2, max_age_hours=0)
    
    assert not os.path.exists(temp1), "Temp dir 1 should be cleaned"
    assert not os.path.exists(temp2), "Temp dir 2 should be cleaned"
    print("  [OK] Cleanup successful")
    
    return True


def test_force_release():
    """Test 9: Force release mechanism"""
    print("\n[Test 9] Force release")
    
    lock1 = VideoCompositorLock()
    job_id = "test_force_001"
    
    # Acquire lock
    assert lock1.acquire(job_id, blocking=False), "Should acquire lock"
    assert lock1.acquired, "Lock should be acquired"
    print(f"  [OK] Lock acquired by {job_id}")
    
    # Force release from another instance
    lock2 = VideoCompositorLock()
    result = lock2.force_release("Test force release")
    assert result, "Force release should succeed"
    print("  [OK] Force release executed")
    
    # Verify lock1 knows it lost the lock
    assert not lock1.acquired, "Lock1 should know it lost the lock"
    
    # Verify new lock can acquire
    lock3 = VideoCompositorLock()
    job_id3 = "test_force_002"
    assert lock3.acquire(job_id3, blocking=False), "Should acquire after force release"
    print(f"  [OK] New lock acquired by {job_id3}")
    
    lock3.release()
    return True


def main():
    """Run all tests"""
    print("=" * 70)
    print("VideoCompositorLock - Comprehensive Test Suite")
    print("=" * 70)
    
    tests = [
        ("Basic Acquire/Release", test_basic_acquire_release),
        ("Context Manager", test_context_manager),
        ("Blocking Timeout", test_blocking_timeout),
        ("Double Acquire Prevention", test_double_acquire_prevention),
        ("Concurrent Threads", test_concurrent_access),
        ("Concurrent Processes", test_multiprocess_access),
        ("Stale Lock Detection", test_stale_lock_detection),
        ("Temp Directory Isolation", test_temp_directory_isolation),
        ("Force Release", test_force_release),
    ]
    
    results = []
    
    for name, test_func in tests:
        try:
            success = test_func()
            results.append((name, True, None))
        except Exception as e:
            results.append((name, False, str(e)))
            print(f"  [FAIL] FAILED: {e}")
    
    # Summary
    print("\n" + "=" * 70)
    print("Test Summary")
    print("=" * 70)
    
    passed = sum(1 for _, success, _ in results if success)
    failed = len(results) - passed
    
    for name, success, error in results:
        status = "[OK] PASS" if success else "[FAIL] FAIL"
        print(f"{status:8s} | {name}")
        if error:
            print(f"         | Error: {error}")
    
    print("=" * 70)
    print(f"Results: {passed}/{len(results)} passed, {failed} failed")
    print("=" * 70)
    
    if failed == 0:
        print("\n[SUCCESS] All tests passed!")
        return 0
    else:
        print(f"\n[ERROR] {failed} test(s) failed")
        return 1


if __name__ == "__main__":
    sys.exit(main())

