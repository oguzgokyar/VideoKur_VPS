"""
Cross-Process Video Compositor Lock Manager
Prevents parallel video composition conflicts by using file-based locking
Includes temp directory isolation for true parallel processing
"""
import os
import sys
import time
import json
import tempfile
import shutil
from pathlib import Path
from datetime import datetime, timedelta
import platform


class VideoCompositorLock:
    """
    File-based cross-process lock for video composition.
    Prevents MoviePy temp file collisions during parallel video production.
    """
    
    def __init__(self, lock_dir: str = None, timeout: int = 1800, stale_threshold: int = 3600):
        """
        Initialize video compositor lock.
        
        Args:
            lock_dir: Directory to store lock files (default: project data dir)
            timeout: Max seconds to wait for lock (default: 30 minutes)
            stale_threshold: Seconds before a lock is considered stale (default: 1 hour)
        """
        if lock_dir is None:
            # Default to project data directory
            base_dir = Path(__file__).parent.parent.parent
            lock_dir = base_dir / 'data' / '.locks'
        
        self.lock_dir = Path(lock_dir)
        self.lock_dir.mkdir(parents=True, exist_ok=True)
        
        self.lock_file = self.lock_dir / 'video_compositor.lock'
        self.metadata_file = self.lock_dir / 'video_compositor.meta'
        self.timeout = timeout
        self.stale_threshold = stale_threshold
        self.acquired = False
        self.job_id = None
        
    def _is_lock_stale(self) -> bool:
        """Check if existing lock is stale (process crashed/hung)."""
        if not self.metadata_file.exists():
            return True
        
        try:
            with open(self.metadata_file, 'r', encoding='utf-8') as f:
                meta = json.load(f)
            
            lock_time = datetime.fromisoformat(meta.get('acquired_at', ''))
            age = (datetime.now() - lock_time).total_seconds()
            
            if age > self.stale_threshold:
                print(f"  [Lock] Stale lock detected (age: {age:.0f}s), will clean up")
                return True
            
            # Check if PID still exists (best effort)
            pid = meta.get('pid')
            if pid and not self._pid_exists(pid):
                print(f"  [Lock] Process {pid} no longer exists, lock is stale")
                return True
                
            return False
        except Exception as e:
            print(f"  [Lock] Error checking stale lock: {e}")
            return True
    
    def _pid_exists(self, pid: int) -> bool:
        """Check if process with given PID exists (cross-platform)."""
        try:
            if platform.system() == 'Windows':
                import ctypes
                PROCESS_QUERY_INFORMATION = 0x0400
                handle = ctypes.windll.kernel32.OpenProcess(PROCESS_QUERY_INFORMATION, 0, pid)
                if handle:
                    ctypes.windll.kernel32.CloseHandle(handle)
                    return True
                return False
            else:
                # Unix-like systems
                os.kill(pid, 0)
                return True
        except (OSError, AttributeError):
            return False
    
    def _clean_stale_lock(self):
        """Remove stale lock files."""
        try:
            if self.lock_file.exists():
                os.remove(self.lock_file)
            if self.metadata_file.exists():
                os.remove(self.metadata_file)
            print("  [Lock] Stale lock cleaned up")
        except Exception as e:
            print(f"  [Lock] Error cleaning stale lock: {e}")
    
    def _write_metadata(self, job_id: str):
        """Write lock metadata for monitoring."""
        meta = {
            'job_id': job_id,
            'pid': os.getpid(),
            'acquired_at': datetime.now().isoformat(),
            'hostname': platform.node()
        }
        try:
            with open(self.metadata_file, 'w', encoding='utf-8') as f:
                json.dump(meta, f, indent=2)
        except Exception as e:
            print(f"  [Lock] Warning: Could not write metadata: {e}")
    
    def _read_metadata(self) -> dict:
        """Read current lock metadata."""
        try:
            if self.metadata_file.exists():
                with open(self.metadata_file, 'r', encoding='utf-8') as f:
                    return json.load(f)
        except Exception:
            pass
        return {}
    
    def acquire(self, job_id: str, blocking: bool = True, timeout: float = None) -> bool:
        """
        Acquire the video compositor lock.
        
        Args:
            job_id: Job ID requesting the lock
            blocking: If True, wait for lock. If False, return immediately
            timeout: Max seconds to wait (overrides instance timeout if provided)
            
        Returns:
            True if lock acquired, False otherwise
            
        Raises:
            TimeoutError: If timeout exceeded while waiting for lock
        """
        if self.acquired:
            return True
        
        # Use provided timeout or fall back to instance timeout
        max_wait = timeout if timeout is not None else self.timeout
        
        start_time = time.time()
        wait_logged = False
        
        while True:
            # Check if lock file exists
            if self.lock_file.exists():
                # Check if lock is stale
                if self._is_lock_stale():
                    self._clean_stale_lock()
                else:
                    # Lock is held by another process
                    if not blocking:
                        return False
                    
                    # Log waiting status
                    if not wait_logged:
                        meta = self._read_metadata()
                        holder = meta.get('job_id', 'unknown')
                        print(f"  [Lock] Job {job_id} waiting for video compositor (held by {holder})...")
                        wait_logged = True
                    
                    # Check timeout
                    elapsed = time.time() - start_time
                    if elapsed > max_wait:
                        meta = self._read_metadata()
                        holder = meta.get('job_id', 'unknown')
                        raise TimeoutError(
                            f"Timeout waiting for video compositor lock after {elapsed:.0f}s. "
                            f"Lock held by job {holder}"
                        )
                    
                    # Wait and retry
                    time.sleep(2)
                    continue
            
            # Try to acquire lock
            try:
                # Atomic lock file creation
                fd = os.open(self.lock_file, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
                os.write(fd, job_id.encode('utf-8'))
                os.close(fd)
                
                # Write metadata
                self._write_metadata(job_id)
                
                self.acquired = True
                self.job_id = job_id
                
                elapsed = time.time() - start_time
                if elapsed > 5:
                    print(f"  [Lock] Job {job_id} acquired video compositor lock (waited {elapsed:.1f}s)")
                else:
                    print(f"  [Lock] Job {job_id} acquired video compositor lock")
                
                return True
                
            except FileExistsError:
                # Race condition: another process created lock first
                if not blocking:
                    return False
                time.sleep(0.1)
                continue
            except Exception as e:
                print(f"  [Lock] Error acquiring lock: {e}")
                return False
    
    def release(self):
        """Release the video compositor lock."""
        if not self.acquired:
            return
        
        try:
            if self.lock_file.exists():
                os.remove(self.lock_file)
            if self.metadata_file.exists():
                os.remove(self.metadata_file)
            
            print(f"  [Lock] Job {self.job_id} released video compositor lock")
            self.acquired = False
            self.job_id = None
            
        except Exception as e:
            print(f"  [Lock] Error releasing lock: {e}")
            # Force cleanup even on error
            try:
                if self.lock_file.exists():
                    os.remove(self.lock_file)
            except:
                pass
            try:
                if self.metadata_file.exists():
                    os.remove(self.metadata_file)
            except:
                pass
            self.acquired = False
            self.job_id = None
    
    def __enter__(self):
        """Context manager support - automatically acquires lock."""
        # Note: job_id must be set before using context manager
        if not self.job_id:
            raise ValueError("job_id must be set before using context manager. Use: lock.job_id = 'your_job_id'")
        self.acquire(self.job_id, blocking=True)
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """Ensure lock is released even on exception."""
        self.release()
        return False
    
    def get_queue_info(self) -> dict:
        """Get information about current lock status."""
        if not self.lock_file.exists():
            return {
                'locked': False,
                'holder': None,
                'acquired_at': None,
                'age_seconds': 0
            }
        
        meta = self._read_metadata()
        acquired_at = meta.get('acquired_at')
        age = 0
        if acquired_at:
            try:
                lock_time = datetime.fromisoformat(acquired_at)
                age = (datetime.now() - lock_time).total_seconds()
            except Exception:
                pass
        
        return {
            'locked': True,
            'holder': meta.get('job_id'),
            'pid': meta.get('pid'),
            'acquired_at': acquired_at,
            'age_seconds': age,
            'is_stale': self._is_lock_stale()
        }
    
    def force_release(self, reason: str = "Manual intervention") -> bool:
        """
        Force release the lock (admin/emergency use).
        Use with caution - only when you're sure the holding process is dead.
        
        Args:
            reason: Reason for force release (logged)
            
        Returns:
            True if lock was released, False if no lock existed
        """
        if not self.lock_file.exists():
            print(f"  [Lock] No lock to release")
            return False
        
        meta = self._read_metadata()
        holder = meta.get('job_id', 'unknown')
        pid = meta.get('pid', 'unknown')
        
        print(f"  [Lock] Force releasing lock held by job {holder} (PID: {pid})")
        print(f"  [Lock] Reason: {reason}")
        
        self._clean_stale_lock()
        return True
    
    def extend_timeout(self, additional_seconds: int):
        """
        Extend the timeout for current lock holder.
        Useful for long-running operations.
        
        Args:
            additional_seconds: Additional seconds to add to timeout
        """
        if not self.acquired:
            raise RuntimeError("Cannot extend timeout - lock not acquired by this instance")
        
        self.timeout += additional_seconds
        print(f"  [Lock] Timeout extended by {additional_seconds}s (new timeout: {self.timeout}s)")
    
    def refresh(self):
        """
        Refresh lock metadata (update timestamp).
        Call this periodically for very long operations to prevent stale lock detection.
        """
        if not self.acquired:
            raise RuntimeError("Cannot refresh - lock not acquired by this instance")
        
        self._write_metadata(self.job_id)
        print(f"  [Lock] Lock refreshed for job {self.job_id}")


def get_lock_status(lock_dir: str = None) -> dict:
    """
    Get current lock status without acquiring it.
    Useful for monitoring and debugging.
    """
    lock = VideoCompositorLock(lock_dir=lock_dir)
    return lock.get_queue_info()


def setup_job_temp_dir(job_id: str) -> str:
    """
    Create isolated temp directory for a job's MoviePy operations.
    Prevents temp file collisions between parallel jobs.
    
    Args:
        job_id: Job ID
        
    Returns:
        Path to job-specific temp directory
        
    Usage:
        temp_dir = setup_job_temp_dir(job_id)
        os.environ['TEMP'] = temp_dir
        os.environ['TMP'] = temp_dir
        # ... run MoviePy operations ...
        cleanup_job_temp_dir(temp_dir)
    """
    base_temp = tempfile.gettempdir()
    job_temp = os.path.join(base_temp, 'video_kur', f'job_{job_id}')
    
    # Create directory
    os.makedirs(job_temp, exist_ok=True)
    
    return job_temp


def cleanup_job_temp_dir(job_id_or_path: str, max_age_hours: int = 24):
    """
    Clean up job-specific temp directory.
    
    Args:
        job_id_or_path: Job ID or full path to temp directory
        max_age_hours: Only clean if older than this many hours (0 = force clean)
    """
    try:
        # Determine if input is job_id or full path
        if os.path.isabs(job_id_or_path):
            temp_dir = job_id_or_path
        else:
            # It's a job_id, construct path
            base_temp = tempfile.gettempdir()
            temp_dir = os.path.join(base_temp, 'video_kur', f'job_{job_id_or_path}')
        
        if not os.path.exists(temp_dir):
            return
        
        # Check age (0 = force clean)
        if max_age_hours > 0:
            dir_age = time.time() - os.path.getmtime(temp_dir)
            if dir_age < max_age_hours * 3600:
                # Too recent, skip
                return
        
        # Remove directory
        shutil.rmtree(temp_dir, ignore_errors=True)
        print(f"  [TempClean] Removed temp dir: {temp_dir}")
    except Exception as e:
        print(f"  [TempClean] Error cleaning temp dir: {e}")


def cleanup_all_stale_temp_dirs(max_age_hours: int = 24):
    """
    Clean up all stale job temp directories.
    Call this periodically (e.g., daily cron job).
    
    Args:
        max_age_hours: Remove dirs older than this
    """
    try:
        base_temp = tempfile.gettempdir()
        video_kur_temp = os.path.join(base_temp, 'video_kur')
        
        if not os.path.exists(video_kur_temp):
            return
        
        cleaned = 0
        for item in os.listdir(video_kur_temp):
            item_path = os.path.join(video_kur_temp, item)
            if os.path.isdir(item_path):
                cleanup_job_temp_dir(item_path, max_age_hours)
                if not os.path.exists(item_path):
                    cleaned += 1
        
        if cleaned > 0:
            print(f"  [TempClean] Cleaned {cleaned} stale temp directories")
    except Exception as e:
        print(f"  [TempClean] Error during cleanup: {e}")


if __name__ == '__main__':
    # Test and diagnostics
    import argparse
    
    parser = argparse.ArgumentParser(description='Video Compositor Lock - Diagnostics & Management')
    parser.add_argument('--status', action='store_true', help='Show current lock status')
    parser.add_argument('--force-release', action='store_true', help='Force release the lock')
    parser.add_argument('--cleanup-temp', action='store_true', help='Clean up stale temp directories')
    parser.add_argument('--test', action='store_true', help='Run basic functionality test')
    args = parser.parse_args()
    
    # Default: show status
    if not any([args.force_release, args.cleanup_temp, args.test]):
        args.status = True
    
    if args.status:
        print("Video Compositor Lock - Status")
        print("=" * 60)
        
        status = get_lock_status()
        print(f"Locked: {status['locked']}")
        if status['locked']:
            print(f"Holder: {status['holder']}")
            print(f"PID: {status['pid']}")
            print(f"Age: {status['age_seconds']:.1f}s")
            print(f"Stale: {status['is_stale']}")
            print(f"Acquired at: {status['acquired_at']}")
        else:
            print("No active lock")
    
    if args.force_release:
        print("\n" + "=" * 60)
        print("Force Release Lock")
        print("=" * 60)
        lock = VideoCompositorLock()
        if lock.force_release("Manual force release via CLI"):
            print("✓ Lock released")
        else:
            print("✓ No lock to release")
    
    if args.cleanup_temp:
        print("\n" + "=" * 60)
        print("Cleaning Stale Temp Directories")
        print("=" * 60)
        cleanup_all_stale_temp_dirs(max_age_hours=24)
        print("✓ Cleanup complete")
    
    if args.test:
        print("\n" + "=" * 60)
        print("Running Basic Functionality Test")
        print("=" * 60)
        
        test_job_id = "test_job_123"
        
        # Test 1: Acquire and release
        print("\nTest 1: Acquire and release")
        lock1 = VideoCompositorLock()
        assert lock1.acquire(test_job_id, blocking=False), "Failed to acquire lock"
        print("✓ Lock acquired")
        
        status = lock1.get_queue_info()
        assert status['locked'], "Lock should be active"
        assert status['holder'] == test_job_id, "Holder mismatch"
        print("✓ Lock status correct")
        
        lock1.release()
        assert not lock1.acquired, "Lock should be released"
        print("✓ Lock released")
        
        # Test 2: Context manager
        print("\nTest 2: Context manager")
        lock2 = VideoCompositorLock()
        lock2.job_id = test_job_id
        with lock2:
            assert lock2.acquired, "Lock should be acquired in context"
            print("✓ Context manager acquired lock")
        assert not lock2.acquired, "Lock should be released after context"
        print("✓ Context manager released lock")
        
        # Test 3: Temp directory
        print("\nTest 3: Temp directory isolation")
        temp_dir = setup_job_temp_dir(test_job_id)
        assert os.path.exists(temp_dir), "Temp dir should exist"
        print(f"✓ Temp dir created: {temp_dir}")
        
        # Write test file
        test_file = os.path.join(temp_dir, 'test.txt')
        with open(test_file, 'w') as f:
            f.write('test')
        assert os.path.exists(test_file), "Test file should exist"
        print("✓ Can write to temp dir")
        
        # Cleanup (force immediate cleanup)
        shutil.rmtree(temp_dir, ignore_errors=True)
        print("✓ Temp dir cleaned up")
        
        print("\n" + "=" * 60)
        print("✓ All tests passed!")
        print("=" * 60)
