"""
Global Production Lock Manager
Ensures only one video production process runs at a time across the entire system
"""
import os
import sys
import time
import json
import platform
from pathlib import Path
from datetime import datetime, timezone
from typing import Optional


class GlobalProductionLock:
    """
    Global file-based lock for video production.
    Ensures only ONE video production runs at a time (no parallelism).
    """
    
    def __init__(self, lock_dir: str = None, timeout: int = 3600, stale_threshold: int = 7200):
        """
        Initialize global production lock.
        
        Args:
            lock_dir: Directory to store lock files (default: project data/.locks)
            timeout: Max seconds to wait for lock (default: 1 hour)
            stale_threshold: Seconds before a lock is considered stale (default: 2 hours)
        """
        if lock_dir is None:
            # Default to project data/.locks directory
            base_dir = Path(__file__).parent.parent.parent
            lock_dir = base_dir / 'data' / '.locks'
        
        self.lock_dir = Path(lock_dir)
        self.lock_dir.mkdir(parents=True, exist_ok=True)
        
        self.lock_file = self.lock_dir / 'production.lock'
        self.metadata_file = self.lock_dir / 'production.meta'
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
            age = (datetime.now(timezone.utc) - lock_time.replace(tzinfo=timezone.utc)).total_seconds()
            
            if age > self.stale_threshold:
                print(f"[LOCK] Stale lock detected (age: {age:.0f}s), will clean up")
                return True
            
            # Check if PID still exists
            pid = meta.get('pid')
            if pid and not self._pid_exists(pid):
                print(f"[LOCK] Process {pid} no longer exists, lock is stale")
                return True
                
            return False
        except Exception as e:
            print(f"[LOCK] Error checking stale lock: {e}")
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
            print("[LOCK] Stale lock cleaned up")
        except Exception as e:
            print(f"[LOCK] Error cleaning stale lock: {e}")
    
    def _write_metadata(self, job_id: str):
        """Write lock metadata for monitoring."""
        meta = {
            'job_id': job_id,
            'pid': os.getpid(),
            'acquired_at': datetime.now(timezone.utc).isoformat(),
            'hostname': platform.node(),
            'type': 'production'
        }
        try:
            with open(self.metadata_file, 'w', encoding='utf-8') as f:
                json.dump(meta, f, indent=2)
        except Exception as e:
            print(f"[LOCK] Warning: Could not write metadata: {e}")
    
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
        Acquire the global production lock.
        
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
            try:
                # Check if lock exists
                if self.lock_file.exists():
                    # Check if it's stale
                    if self._is_lock_stale():
                        self._clean_stale_lock()
                    else:
                        # Lock exists and is valid
                        if not blocking:
                            return False
                        
                        # Show which job is holding the lock
                        if not wait_logged:
                            meta = self._read_metadata()
                            current_job = meta.get('job_id', 'unknown')
                            current_pid = meta.get('pid', 'unknown')
                            print(f"[LOCK] Waiting for production lock...")
                            print(f"[LOCK] Currently held by job: {current_job} (PID: {current_pid})")
                            wait_logged = True
                        
                        # Check timeout
                        elapsed = time.time() - start_time
                        if elapsed >= max_wait:
                            raise TimeoutError(
                                f"Timeout waiting for production lock after {elapsed:.1f}s. "
                                f"Currently held by: {meta.get('job_id', 'unknown')}"
                            )
                        
                        # Wait and retry
                        time.sleep(2)
                        continue
                
                # Try to create lock file (atomic operation)
                # Use 'x' mode which fails if file exists (atomic on most systems)
                try:
                    with open(self.lock_file, 'x') as f:
                        f.write(job_id)
                    
                    # Write metadata
                    self._write_metadata(job_id)
                    
                    self.acquired = True
                    self.job_id = job_id
                    
                    print(f"[LOCK] Production lock acquired for job: {job_id}")
                    return True
                    
                except FileExistsError:
                    # Race condition - another process created the lock
                    if not blocking:
                        return False
                    
                    # Loop will retry
                    time.sleep(0.1)
                    continue
                    
            except TimeoutError:
                raise
            except Exception as e:
                print(f"[LOCK] Error acquiring lock: {e}")
                if not blocking:
                    return False
                time.sleep(1)
    
    def release(self):
        """Release the global production lock."""
        if not self.acquired:
            return
        
        try:
            if self.lock_file.exists():
                os.remove(self.lock_file)
            if self.metadata_file.exists():
                os.remove(self.metadata_file)
            
            print(f"[LOCK] Production lock released for job: {self.job_id}")
            
            self.acquired = False
            self.job_id = None
            
        except Exception as e:
            print(f"[LOCK] Error releasing lock: {e}")
    
    def is_locked(self) -> bool:
        """Check if production lock is currently held."""
        if not self.lock_file.exists():
            return False
        
        # Check if stale
        if self._is_lock_stale():
            return False
        
        return True
    
    def get_current_job(self) -> Optional[str]:
        """Get the job ID currently holding the lock."""
        if not self.is_locked():
            return None
        
        meta = self._read_metadata()
        return meta.get('job_id')
    
    def __enter__(self):
        """Context manager entry."""
        if not self.job_id:
            raise ValueError("job_id must be set before using context manager")
        self.acquire(self.job_id)
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """Context manager exit."""
        self.release()
        return False


# Convenience functions
def acquire_production_lock(job_id: str, blocking: bool = True, timeout: float = None) -> GlobalProductionLock:
    """Acquire global production lock"""
    lock = GlobalProductionLock()
    lock.acquire(job_id, blocking, timeout)
    return lock


def is_production_locked() -> bool:
    """Check if any production is currently running"""
    lock = GlobalProductionLock()
    return lock.is_locked()


def get_current_production() -> Optional[str]:
    """Get currently running production job ID"""
    lock = GlobalProductionLock()
    return lock.get_current_job()


if __name__ == '__main__':
    # Test the lock
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python production_lock.py <job_id> [timeout]")
        sys.exit(1)
    
    job_id = sys.argv[1]
    timeout = int(sys.argv[2]) if len(sys.argv) > 2 else 10
    
    print(f"Testing production lock for job: {job_id}")
    print(f"Timeout: {timeout}s")
    
    lock = GlobalProductionLock()
    
    try:
        print("\nAttempting to acquire lock...")
        if lock.acquire(job_id, blocking=True, timeout=timeout):
            print("✅ Lock acquired!")
            print("\nHolding lock for 5 seconds...")
            time.sleep(5)
            print("\nReleasing lock...")
            lock.release()
            print("✅ Lock released!")
        else:
            print("❌ Failed to acquire lock (non-blocking)")
    except TimeoutError as e:
        print(f"❌ Timeout: {e}")
    except KeyboardInterrupt:
        print("\n\nInterrupted - releasing lock...")
        lock.release()
